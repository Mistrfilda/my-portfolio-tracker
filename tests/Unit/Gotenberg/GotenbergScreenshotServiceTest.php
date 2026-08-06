<?php

declare(strict_types = 1);

namespace App\Test\Unit\Gotenberg;

use App\Gotenberg\GotenbergScreenshotService;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class GotenbergScreenshotServiceTest extends TestCase
{

	public function testCaptureHtmlReturnsPngResponse(): void
	{
		$psr17Factory = new Psr17Factory();
		$response = $psr17Factory
			->createResponse(200)
			->withHeader('Content-Type', 'image/png')
			->withBody($psr17Factory->createStream('png-content'));
		$client = $this->createMock(ClientInterface::class);
		$client
			->expects($this->once())
			->method('sendRequest')
			->with($this->callback(static function (RequestInterface $request): bool {
				self::assertSame(
					'http://gotenberg:3000/forms/chromium/screenshot/html',
					(string) $request->getUri(),
				);
				self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
				self::assertSame('image/png', $request->getHeaderLine('Accept'));
				$requestBody = (string) $request->getBody();
				self::assertStringContainsString('filename="index.html"', $requestBody);
				self::assertStringContainsString('<h1>Report</h1>', $requestBody);
				self::assertStringContainsString('name="width"', $requestBody);
				self::assertStringContainsString("\r\n\r\n1200", $requestBody);
				self::assertStringContainsString('name="format"', $requestBody);
				self::assertStringContainsString("\r\n\r\npng", $requestBody);

				return true;
			}))
			->willReturn($response);
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory
			->expects($this->once())
			->method('getClient')
			->with(['connect_timeout' => 5, 'timeout' => 30])
			->willReturn($client);

		$service = new GotenbergScreenshotService(
			$clientFactory,
			new Psr7RequestFactory(),
			'http://gotenberg:3000/',
		);

		self::assertSame('png-content', $service->captureHtml('<h1>Report</h1>'));
	}

	public function testCaptureHtmlRejectsErrorResponse(): void
	{
		$psr17Factory = new Psr17Factory();
		$response = $psr17Factory
			->createResponse(503)
			->withBody($psr17Factory->createStream('service unavailable'));
		$client = $this->createStub(ClientInterface::class);
		$client->method('sendRequest')->willReturn($response);
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);
		$service = new GotenbergScreenshotService(
			$clientFactory,
			new Psr7RequestFactory(),
			'http://gotenberg:3000',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Gotenberg screenshot request failed with HTTP 503: service unavailable.',
		);

		$service->captureHtml('<h1>Report</h1>');
	}

}
