<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Gotenberg\GotenbergScreenshotService;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Notification\Discord\AssetTrendReportRenderer;
use App\Notification\Discord\DiscordChannelService;
use App\Notification\Discord\DiscordMessageService;
use App\Notification\Discord\NotificationDiscordSenderFacade;
use App\Notification\Notification;
use App\Notification\NotificationTypeEnum;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class NotificationDiscordSenderFacadeTest extends TestCase
{

	public function testSendUploadsAssetTrendReportAsEmbedImage(): void
	{
		$notification = $this->createStub(Notification::class);
		$notification
			->method('getNotificationTypeEnum')
			->willReturn(NotificationTypeEnum::ASSET_TRENDS);
		$channelService = $this->createMock(DiscordChannelService::class);
		$channelService
			->expects($this->once())
			->method('getWebhookUrl')
			->with($notification)
			->willReturn('https://discord.example/webhook');
		$message = [
			'embeds' => [['image' => ['url' => 'attachment://asset-trends.png']]],
			'attachments' => [['id' => 0, 'filename' => 'asset-trends.png']],
		];
		$messageService = $this->createMock(DiscordMessageService::class);
		$messageService
			->expects($this->once())
			->method('getMessage')
			->with($notification)
			->willReturn($message);
		$reportRenderer = $this->createMock(AssetTrendReportRenderer::class);
		$reportRenderer
			->expects($this->once())
			->method('render')
			->with($notification)
			->willReturn('<html lang="cs">report</html>');
		$gotenbergScreenshotService = $this->createMock(GotenbergScreenshotService::class);
		$gotenbergScreenshotService
			->expects($this->once())
			->method('captureHtml')
			->with('<html lang="cs">report</html>')
			->willReturn('png-content');
		$client = $this->createMock(ClientInterface::class);
		$client
			->expects($this->once())
			->method('sendRequest')
			->with($this->callback(static function (RequestInterface $request): bool {
				self::assertSame('https://discord.example/webhook', (string) $request->getUri());
				self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
				$requestBody = (string) $request->getBody();
				self::assertStringContainsString('name="payload_json"', $requestBody);
				self::assertStringContainsString('attachment://asset-trends.png', $requestBody);
				self::assertStringContainsString('name="files[0]"; filename="asset-trends.png"', $requestBody);
				self::assertStringContainsString('png-content', $requestBody);

				return true;
			}))
			->willReturn(new Psr17Factory()->createResponse(204));
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);

		$sender = new NotificationDiscordSenderFacade(
			$clientFactory,
			new Psr7RequestFactory(),
			$messageService,
			$channelService,
			$reportRenderer,
			$gotenbergScreenshotService,
		);

		$sender->send($notification);
	}

}
