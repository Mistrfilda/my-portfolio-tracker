<?php

declare(strict_types = 1);

namespace App\Test\Unit\Ai\Gemini;

use App\Ai\AiClient;
use App\Ai\AiClientException;
use App\Ai\AiModel;
use App\Ai\DefaultAiModelEnum;
use App\Ai\Gemini\GeminiClient;
use App\Ai\Gemini\GeminiClientException;
use App\Ai\Gemini\GeminiModelEnum;
use App\Ai\OpenAi\OpenAiModelEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Test\UpdatedTestCase;
use Nette\Utils\Json;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
					'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent',
					(string) $request->getUri(),
				);
				self::assertSame('test-api-key', $request->getHeaderLine('x-goog-api-key'));
				self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

				$requestBody = $request->getBody()->getContents();
				$body = Json::decode($requestBody, true);
				self::assertSame('Analyze portfolio', $body['contents'][0]['parts'][0]['text']);
				self::assertArrayNotHasKey('generationConfig', $body);
				self::assertArrayNotHasKey('systemInstruction', $body);
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
			$psr18ClientFactory,
			new Psr7RequestFactory(),
			$this->createMock(LoggerInterface::class),
		);

		self::assertInstanceOf(AiClient::class, $client);
		self::assertSame('{"marketOverview":{}}', $client->generateContent('Analyze portfolio'));
	}

	public function testGenerateContentSendsResponseSchemaWhenProvided(): void
	{
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$psr18Client = $this->createMock(ClientInterface::class);
		$psr18ClientFactory->method('getClient')->willReturn($psr18Client);
		$responseSchema = [
			'type' => 'OBJECT',
			'properties' => [
				'marketOverview' => [
					'type' => 'OBJECT',
					'properties' => [
						'summary' => [
							'type' => 'STRING',
						],
					],
				],
			],
			'required' => [
				'marketOverview',
			],
		];

		$psr18Client->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request) use ($responseSchema): bool {
				$body = Json::decode($request->getBody()->getContents(), true);
				self::assertSame('application/json', $body['generationConfig']['responseMimeType']);
				self::assertSame($responseSchema, $body['generationConfig']['responseSchema']);
				self::assertArrayNotHasKey('temperature', $body['generationConfig']);
				self::assertArrayNotHasKey('responseFormat', $body['generationConfig']);

				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'candidates' => [
					[
						'content' => [
							'parts' => [
								[
									'text' => '{"marketOverview":{"summary":"Summary"}}',
								],
							],
						],
					],
				],
			])));

		$client = new GeminiClient(
			'test-api-key',
			$psr18ClientFactory,
			new Psr7RequestFactory(),
			$this->createMock(LoggerInterface::class),
		);

		self::assertSame(
			'{"marketOverview":{"summary":"Summary"}}',
			$client->generateContent('Analyze portfolio', responseSchema: $responseSchema),
		);
	}

	public function testGenerateContentSendsSystemInstructionWhenProvided(): void
	{
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$psr18Client = $this->createMock(ClientInterface::class);
		$psr18ClientFactory->method('getClient')->willReturn($psr18Client);

		$psr18Client->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				$body = Json::decode($request->getBody()->getContents(), true);
				self::assertSame('Analyze portfolio', $body['contents'][0]['parts'][0]['text']);
				self::assertSame('Answer as a stock analyst.', $body['systemInstruction']['parts'][0]['text']);
				self::assertArrayNotHasKey('generationConfig', $body);

				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'candidates' => [
					[
						'content' => [
							'parts' => [
								[
									'text' => '{"marketOverview":{}}',
								],
							],
						],
					],
				],
			])));

		$client = new GeminiClient(
			'test-api-key',
			$psr18ClientFactory,
			new Psr7RequestFactory(),
			$this->createMock(LoggerInterface::class),
		);

		self::assertSame(
			'{"marketOverview":{}}',
			$client->generateContent('Analyze portfolio', 'Answer as a stock analyst.'),
		);
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

	#[DataProvider('provideModels')]
	public function testSelectsDefaultOrExplicitFlashModel(AiModel $model): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				self::assertSame(
					'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent',
					(string) $request->getUri(),
				);

				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'candidates' => [['content' => ['parts' => [['text' => 'Result']]]]],
			])));

		self::assertSame('Result', $this->createClient($httpClient)->generateContent('Prompt', model: $model));
	}

	/**
	 * @return iterable<string, array{AiModel}>
	 */
	public static function provideModels(): iterable
	{
		yield 'provider default' => [DefaultAiModelEnum::DEFAULT];
		yield 'Flash' => [GeminiModelEnum::FLASH];
	}

	#[DataProvider('provideOpenAiModels')]
	public function testRejectsOpenAiModelBeforeSendingRequest(OpenAiModelEnum $model): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::never())->method('sendRequest');

		$this->expectException(GeminiClientException::class);
		$this->expectExceptionMessage('Gemini client requires a Gemini model.');

		$this->createClient($httpClient)->generateContent('Prompt', model: $model);
	}

	/**
	 * @return iterable<string, array{OpenAiModelEnum}>
	 */
	public static function provideOpenAiModels(): iterable
	{
		foreach (OpenAiModelEnum::cases() as $model) {
			yield $model->name => [$model];
		}
	}

	public function testGenerateContentThrowsExceptionForNonSuccessStatusCode(): void
	{
		$psr18ClientFactory = $this->createMock(Psr18ClientFactory::class);
		$psr18Client = $this->createMock(ClientInterface::class);
		$psr18ClientFactory->method('getClient')->willReturn($psr18Client);
		$psr18Client->method('sendRequest')->willReturn(new Response(500, [], '{}'));

		$client = new GeminiClient(
			'test-api-key',
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

	#[DataProvider('provideInvalidResponses')]
	public function testResponseErrorsCanBeCaughtAsCommonClientException(string $body, string $expectedMessage): void
	{
		$httpClient = $this->createStub(ClientInterface::class);
		$httpClient->method('sendRequest')->willReturn(new Response(200, [], $body));
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($httpClient);
		$client = new GeminiClient(
			'test-api-key',
			$factory,
			new Psr7RequestFactory(),
			new NullLogger(),
		);

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage($expectedMessage);

		$client->generateContent('Prompt');
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideInvalidResponses(): iterable
	{
		yield 'invalid JSON' => ['not-json', 'Gemini generateContent response is not valid JSON.'];
		yield 'invalid shape' => ['null', 'Gemini generateContent response has invalid shape.'];
		yield 'missing text' => [Json::encode([
			'candidates' => [['content' => ['parts' => []]]],
		]), 'Gemini generateContent response does not contain text.'];
	}

	private function createClient(ClientInterface $httpClient): AiClient
	{
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($httpClient);

		return new GeminiClient('test-api-key', $factory, new Psr7RequestFactory(), new NullLogger());
	}

}
