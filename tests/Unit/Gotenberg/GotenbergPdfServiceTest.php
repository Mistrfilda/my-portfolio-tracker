<?php

declare(strict_types = 1);

namespace App\Test\Unit\Gotenberg;

use App\Gotenberg\GotenbergPdfService;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class GotenbergPdfServiceTest extends TestCase
{

	public function testConvertHtmlReturnsPdfResponse(): void
	{
		$psr17Factory = new Psr17Factory();
		$response = $psr17Factory
			->createResponse(200)
			->withHeader('Content-Type', 'application/pdf; charset=binary')
			->withBody($psr17Factory->createStream('pdf-content'));
		$client = $this->createMock(ClientInterface::class);
		$client
			->expects($this->once())
			->method('sendRequest')
			->with($this->callback(static function (RequestInterface $request): bool {
				self::assertSame(
					'http://gotenberg:3000/forms/chromium/convert/html',
					(string) $request->getUri(),
				);
				self::assertSame('application/pdf', $request->getHeaderLine('Accept'));
				self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));

				$requestBody = (string) $request->getBody();
				self::assertStringContainsString('filename="index.html"', $requestBody);
				self::assertStringContainsString('filename="header.html"', $requestBody);
				self::assertStringContainsString('filename="footer.html"', $requestBody);
				self::assertStringContainsString('<h1>Report</h1>', $requestBody);
				self::assertStringContainsString('name="paperWidth"', $requestBody);
				self::assertStringContainsString("\r\n\r\n8.27", $requestBody);
				self::assertStringContainsString('name="printBackground"', $requestBody);
				self::assertStringContainsString("\r\n\r\ntrue", $requestBody);
				self::assertStringContainsString('class="pageNumber"', $requestBody);
				self::assertStringContainsString('class="totalPages"', $requestBody);

				return true;
			}))
			->willReturn($response);
		$clientFactory = $this->createMock(Psr18ClientFactory::class);
		$clientFactory
			->expects($this->once())
			->method('getClient')
			->with(['connect_timeout' => 5, 'timeout' => 120])
			->willReturn($client);
		$service = new GotenbergPdfService(
			$clientFactory,
			new Psr7RequestFactory(),
			'http://gotenberg:3000/',
		);

		self::assertSame('pdf-content', $service->convertHtml('<h1>Report</h1>'));
	}

	public function testConvertHtmlRejectsErrorResponse(): void
	{
		$psr17Factory = new Psr17Factory();
		$response = $psr17Factory
			->createResponse(503)
			->withBody($psr17Factory->createStream('service unavailable'));
		$client = $this->createStub(ClientInterface::class);
		$client->method('sendRequest')->willReturn($response);
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);
		$service = new GotenbergPdfService(
			$clientFactory,
			new Psr7RequestFactory(),
			'http://gotenberg:3000',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Gotenberg PDF request failed with HTTP 503: service unavailable.');

		$service->convertHtml('<h1>Report</h1>');
	}

	public function testConvertHtmlRejectsUnexpectedContentType(): void
	{
		$psr17Factory = new Psr17Factory();
		$response = $psr17Factory
			->createResponse(200)
			->withHeader('Content-Type', 'text/plain');
		$client = $this->createStub(ClientInterface::class);
		$client->method('sendRequest')->willReturn($response);
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);
		$service = new GotenbergPdfService(
			$clientFactory,
			new Psr7RequestFactory(),
			'http://gotenberg:3000',
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Gotenberg PDF response has unexpected content type "text/plain".');

		$service->convertHtml('<h1>Report</h1>');
	}

}
