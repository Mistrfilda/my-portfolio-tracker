<?php

declare(strict_types = 1);

namespace App\Test\Unit\Ai\Gemini;

use App\Ai\Gemini\GeminiClient;
use App\Ai\Gemini\GeminiClientException;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Test\UpdatedTestCase;
use Nette\Utils\Json;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class GeminiClientTest extends UpdatedTestCase
{

	public function testGenerateContentSendsExpectedPayloadAndReturnsText(): void
	{
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$psr18Client = $this->createMock(ClientInterface::class);
		$psr18ClientFactory->method('getClient')->willReturn($psr18Client);

		$psr18Client->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				self::assertSame('POST', $request->getMethod());
				self::assertSame(
					'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
					(string) $request->getUri(),
				);
				self::assertSame('test-api-key', $request->getHeaderLine('x-goog-api-key'));
				self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

				$requestBody = $request->getBody()->getContents();
				$body = Json::decode($requestBody, true);
				self::assertSame('Analyze portfolio', $body['contents'][0]['parts'][0]['text']);
				self::assertStringContainsString('"google_search":{}', $requestBody);

				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'candidates' => [
					[
						'content' => [
							'parts' => [
								[
									'text' => '{"marketOverview":',
								],
								[
									'text' => '{}}',
								],
							],
						],
					],
				],
			])));

		$client = new GeminiClient(
			'test-api-key',
			'gemini-2.5-flash',
			$psr18ClientFactory,
			new Psr7RequestFactory(),
			$this->createMock(LoggerInterface::class),
		);

		self::assertSame('{"marketOverview":{}}', $client->generateContent('Analyze portfolio'));
	}

	public function testGenerateContentThrowsExceptionForInvalidResponseShape(): void
	{
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$psr18Client = $this->createMock(ClientInterface::class);
		$psr18ClientFactory->method('getClient')->willReturn($psr18Client);
		$psr18Client->method('sendRequest')->willReturn(new Response(200, [], Json::encode([
			'candidates' => [],
		])));

		$client = new GeminiClient(
			'test-api-key',
			'gemini-2.5-flash',
			$psr18ClientFactory,
			new Psr7RequestFactory(),
			$this->createMock(LoggerInterface::class),
		);

		self::assertException(
			static fn () => $client->generateContent('Analyze portfolio'),
			GeminiClientException::class,
			'Gemini generateContent response has invalid shape.',
		);
	}

	public function testGenerateContentThrowsExceptionForNonSuccessStatusCode(): void
	{
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$psr18Client = $this->createMock(ClientInterface::class);
		$psr18ClientFactory->method('getClient')->willReturn($psr18Client);
		$psr18Client->method('sendRequest')->willReturn(new Response(500, [], '{}'));

		$client = new GeminiClient(
			'test-api-key',
			'gemini-2.5-flash',
			$psr18ClientFactory,
			new Psr7RequestFactory(),
			$this->createMock(LoggerInterface::class),
		);

		self::assertException(
			static fn () => $client->generateContent('Analyze portfolio'),
			GeminiClientException::class,
			'Gemini generateContent returned status code 500.',
		);
	}

}
