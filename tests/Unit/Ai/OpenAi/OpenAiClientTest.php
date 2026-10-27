<?php

declare(strict_types = 1);

namespace App\Test\Unit\Ai\OpenAi;

use App\Ai\AiClient;
use App\Ai\AiClientException;
use App\Ai\AiModel;
use App\Ai\DefaultAiModelEnum;
use App\Ai\Gemini\GeminiModelEnum;
use App\Ai\OpenAi\OpenAiClient;
use App\Ai\OpenAi\OpenAiClientException;
use App\Ai\OpenAi\OpenAiModelEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use GuzzleHttp\Exception\ConnectException;
use Nette\Utils\Json;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class OpenAiClientTest extends TestCase
{

	public function testBoundsHttpConnectionAndRequestDuration(): void
	{
		$http = $this->createStub(ClientInterface::class);
		$http->method('sendRequest')->willReturn(new Response(200, [], Json::encode([
			'status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Result']]]],
		])));
		$factory = $this->createMock(Psr18ClientFactory::class);
		$factory->expects(self::once())->method('getClient')->with(
			['connect_timeout' => 5, 'timeout' => 120],
		)->willReturn($http);
		$client = new OpenAiClient('synthetic', $factory, new Psr7RequestFactory(), new NullLogger());
		self::assertSame('Result', $client->generateContent('Prompt'));
	}

	public function testGenerateContentSendsExpectedPayloadAndCombinesMessageText(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request): bool {
				self::assertSame('POST', $request->getMethod());
				self::assertSame('https://api.openai.com/v1/responses', (string) $request->getUri());
				self::assertSame('Bearer test-api-key', $request->getHeaderLine('Authorization'));
				self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
				self::assertSame([
					'model' => 'gpt-6-luna',
					'input' => 'Summarize this text.',
					'store' => false,
				], Json::decode($request->getBody()->getContents(), true));

				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'status' => 'completed',
				'output' => [
					['type' => 'reasoning', 'summary' => []],
					[
						'type' => 'message',
						'role' => 'assistant',
						'status' => 'completed',
						'content' => [
							['type' => 'output_text', 'text' => 'First ', 'annotations' => []],
							['type' => 'output_text', 'text' => 'part. ', 'annotations' => []],
						],
					],
					[
						'type' => 'message',
						'role' => 'assistant',
						'status' => 'completed',
						'content' => [['type' => 'output_text', 'text' => 'Second part.', 'annotations' => []]],
					],
				],
			])));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(2))->method('info')->willReturnCallback(
			static function (string $message, mixed $context): void {
				self::assertSame(match ($message) {
					'Sending OpenAI response request' => [
						'model' => 'gpt-6-luna',
						'promptLength' => strlen('Summarize this text.'),
					],
					'OpenAI response request finished' => [
						'model' => 'gpt-6-luna',
						'responseLength' => strlen('First part. Second part.'),
					],
				}, $context);
			},
		);

		self::assertSame(
			'First part. Second part.',
			$this->createClient($httpClient, $logger)->generateContent('Summarize this text.'),
		);
	}

	public function testGenerateContentSendsSystemInstructionAndStrictResponseSchema(): void
	{
		$responseSchema = [
			'type' => 'object',
			'properties' => ['summary' => ['type' => 'string']],
			'required' => ['summary'],
			'additionalProperties' => false,
		];
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::once())
			->method('sendRequest')
			->with(self::callback(static function (RequestInterface $request) use ($responseSchema): bool {
				self::assertSame([
					'model' => 'gpt-6-luna',
					'input' => 'Summarize this text.',
					'store' => false,
					'instructions' => 'Answer concisely.',
					'text' => [
						'format' => [
							'type' => 'json_schema',
							'name' => 'response',
							'strict' => true,
							'schema' => $responseSchema,
						],
					],
				], Json::decode($request->getBody()->getContents(), true));

				return true;
			}))
			->willReturn(new Response(200, [], Json::encode([
				'status' => 'completed',
				'output' => [[
					'type' => 'message',
					'content' => [['type' => 'output_text', 'text' => '{"summary":"Short summary."}']],
				]],
			])));

		self::assertSame(
			'{"summary":"Short summary."}',
			$this->createClient($httpClient)->generateContent(
				'Summarize this text.',
				'Answer concisely.',
				$responseSchema,
			),
		);
	}

	#[DataProvider('provideModels')]
	public function testSelectsModelForOneRequestWithoutChangingDefault(AiModel $model, string $expectedModel): void
	{
		$requestedModels = [];
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::exactly(2))->method('sendRequest')->willReturnCallback(
			static function (RequestInterface $request) use (&$requestedModels): Response {
				$body = Json::decode($request->getBody()->getContents(), true);
				$requestedModels[] = $body['model'];
				self::assertSame('Prompt', $body['input']);
				self::assertArrayNotHasKey('temperature', $body);

				return new Response(200, [], Json::encode([
					'status' => 'completed',
					'output' => [[
						'type' => 'message',
						'content' => [['type' => 'output_text', 'text' => 'Result']],
					]],
				]));
			},
		);
		$client = $this->createClient($httpClient);

		self::assertSame('Result', $client->generateContent('Prompt', model: $model));
		self::assertSame('Result', $client->generateContent('Prompt'));
		self::assertSame([$expectedModel, 'gpt-6-luna'], $requestedModels);
	}

	/**
	 * @return iterable<string, array{AiModel, string}>
	 */
	public static function provideModels(): iterable
	{
		yield 'provider default' => [DefaultAiModelEnum::DEFAULT, 'gpt-6-luna'];
		yield 'Luna' => [OpenAiModelEnum::LUNA, 'gpt-6-luna'];
		yield 'Sol' => [OpenAiModelEnum::SOL, 'gpt-6.1-sol'];
		yield 'Astra' => [OpenAiModelEnum::ASTRA, 'gpt-6-astra'];
	}

	public function testRejectsGeminiModelBeforeSendingRequest(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::never())->method('sendRequest');

		$this->expectException(OpenAiClientException::class);
		$this->expectExceptionMessage('OpenAI client requires an OpenAI model.');

		$this->createClient($httpClient)->generateContent('Prompt', model: GeminiModelEnum::FLASH);
	}

	#[DataProvider('provideHttpErrorStatuses')]
	public function testRejectsHttpErrorsWithoutLoggingResponseBody(int $statusCode): void
	{
		$httpClient = $this->createStub(ClientInterface::class);
		$httpClient->method('sendRequest')->willReturn(new Response($statusCode, [], 'private error response'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('error')->with(
			'OpenAI response returned non-success status code',
			['model' => 'gpt-6-luna', 'statusCode' => $statusCode],
		);

		$this->expectException(AiClientException::class);
		$this->expectExceptionMessage(sprintf('OpenAI response returned status code %d.', $statusCode));

		$this->createClient($httpClient, $logger)->generateContent('Private prompt');
	}

	/**
	 * @return iterable<string, array{int}>
	 */
	public static function provideHttpErrorStatuses(): iterable
	{
		yield 'redirect' => [302];
		yield 'unauthorized' => [401];
		yield 'rate limited' => [429];
		yield 'server error' => [500];
	}

	public function testWrapsTransportFailureWithoutLoggingRequestOrException(): void
	{
		$exception = new ConnectException('Private transport details', new Request('POST', 'https://example.com'));
		$httpClient = $this->createStub(ClientInterface::class);
		$httpClient->method('sendRequest')->willThrowException($exception);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('error')->with(
			'OpenAI response HTTP request failed',
			['model' => 'gpt-6-luna'],
		);

		try {
			$this->createClient($httpClient, $logger)->generateContent('Private prompt');
			self::fail('Expected a client exception.');
		} catch (OpenAiClientException $actual) {
			self::assertSame('OpenAI response HTTP request failed.', $actual->getMessage());
			self::assertSame($exception, $actual->getPrevious());
		}
	}

	#[DataProvider('provideInvalidResponses')]
	public function testRejectsInvalidOrUnusableResponses(string $body, string $expectedMessage): void
	{
		$httpClient = $this->createStub(ClientInterface::class);
		$httpClient->method('sendRequest')->willReturn(new Response(200, [], $body));

		$this->expectException(OpenAiClientException::class);
		$this->expectExceptionMessage($expectedMessage);

		$this->createClient($httpClient)->generateContent('Prompt');
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideInvalidResponses(): iterable
	{
		yield 'invalid JSON' => ['not-json', 'OpenAI response is not valid JSON.'];

		foreach ([null, 'text', [], ['status' => 42], ['status' => 'completed']] as $index => $data) {
			yield 'invalid envelope ' . $index => [Json::encode($data), 'OpenAI response has invalid shape.'];
		}

		foreach (['failed', 'incomplete', 'queued', 'in_progress', 'cancelled'] as $status) {
			yield $status => [Json::encode([
				'status' => $status,
				'output' => [[
					'type' => 'message',
					'content' => [['type' => 'output_text', 'text' => 'Partial text']],
				]],
			]), 'OpenAI response did not complete successfully.'];
		}

		foreach ([null, 'text', [null], [[]], [['type' => 'message', 'content' => 'text']]] as $index => $output) {
			yield 'invalid output ' . $index => [Json::encode([
				'status' => 'completed',
				'output' => $output,
			]), 'OpenAI response has invalid shape.'];
		}

		foreach ([null, 42, []] as $index => $text) {
			yield 'invalid text ' . $index => [Json::encode([
				'status' => 'completed',
				'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]],
			]), 'OpenAI response has invalid shape.'];
		}

		foreach ([
			[], [['type' => 'reasoning', 'summary' => []]], [[
				'type' => 'message',
				'content' => [['type' => 'output_text', 'text' => '  ']],
			]]] as $index => $output) {
			yield 'missing text ' . $index => [Json::encode([
				'status' => 'completed',
				'output' => $output,
			]), 'OpenAI response does not contain text.'];
		}

		yield 'refusal after text' => [Json::encode([
			'status' => 'completed',
			'output' => [[
				'type' => 'message',
				'content' => [
					['type' => 'output_text', 'text' => 'Partial text'],
					['type' => 'refusal', 'refusal' => 'Private refusal details'],
				],
			]],
		]), 'OpenAI response was refused.'];
	}

	public function testWrapsInvalidRequestJsonWithoutSendingRequest(): void
	{
		$httpClient = $this->createMock(ClientInterface::class);
		$httpClient->expects(self::never())->method('sendRequest');

		$this->expectException(OpenAiClientException::class);
		$this->expectExceptionMessage('OpenAI response request is not valid JSON.');

		$this->createClient($httpClient)->generateContent("\xB1");
	}

	private function createClient(ClientInterface $httpClient, LoggerInterface|null $logger = null): AiClient
	{
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($httpClient);

		return new OpenAiClient(
			'test-api-key',
			$factory,
			new Psr7RequestFactory(),
			$logger ?? new NullLogger(),
		);
	}

}
