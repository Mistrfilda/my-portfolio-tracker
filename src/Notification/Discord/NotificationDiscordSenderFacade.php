<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Gotenberg\GotenbergScreenshotService;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Notification\Notification;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationChannelSenderFacade;
use App\Notification\NotificationTypeEnum;
use Nette\Utils\Json;
use RuntimeException;

class NotificationDiscordSenderFacade implements NotificationChannelSenderFacade
{

	public function __construct(
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly DiscordMessageService $discordMessageService,
		private readonly DiscordChannelService $discordChannelService,
		private readonly AssetTrendReportRenderer $assetTrendReportRenderer,
		private readonly GotenbergScreenshotService $gotenbergScreenshotService,
	)
	{
	}

	public function send(Notification $notification): void
	{
		$webhookUrl = $this->discordChannelService->getWebhookUrl($notification);

		if ($webhookUrl === null) {
			return;
		}

		$message = $this->discordMessageService->getMessage($notification);
		if ($notification->getNotificationTypeEnum() === NotificationTypeEnum::ASSET_TRENDS) {
			$reportHtml = $this->assetTrendReportRenderer->render($notification);
			$reportImage = $this->gotenbergScreenshotService->captureHtml($reportHtml);
			$request = $this->psr7RequestFactory->createMultipartPOSTRequest(
				$webhookUrl,
				[
					[
						'name' => 'payload_json',
						'contents' => Json::encode($message),
						'headers' => ['Content-Type' => 'application/json'],
					],
					[
						'name' => 'files[0]',
						'contents' => $reportImage,
						'filename' => DiscordMessageService::ASSET_TRENDS_IMAGE_FILENAME,
						'headers' => ['Content-Type' => 'image/png'],
					],
				],
			);
		} else {
			$request = $this->psr7RequestFactory->createPOSTRequest(
				$webhookUrl,
				$message,
				[
					'Content-Type' => 'application/json',
				],
			);
		}

		$response = $this->psr18ClientFactory->getClient()->sendRequest($request);
		if ($response->getStatusCode() >= 400) {
			throw new RuntimeException(sprintf(
				'Discord webhook request failed with HTTP %d.',
				$response->getStatusCode(),
			));
		}
	}

	public function getChannel(): NotificationChannelEnum
	{
		return NotificationChannelEnum::DISCORD;
	}

}
