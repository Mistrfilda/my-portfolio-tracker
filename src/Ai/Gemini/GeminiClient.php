<?php

declare(strict_types = 1);

namespace App\Ai\Gemini;

use App\Ai\AiClient;
use App\Ai\AiModel;
use App\Ai\DefaultAiModelEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Utils\TypeValidator;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class GeminiClient implements AiClient
{

	private const API_URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	public function __construct(
		private readonly string $apiKey,
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly LoggerInterface $logger,
	)
	{
	}

	/**
	 * @param array<string, mixed>|null $responseSchema
	 */
	public function generateContent(
		string $prompt,
		string|null $systemInstruction = null,
		array|null $responseSchema = null,
		AiModel $model = DefaultAiModelEnum::DEFAULT,
	): string
	{
		if ($model === DefaultAiModelEnum::DEFAULT) {
			$model = GeminiModelEnum::DEFAULT;
		}

		if (!$model instanceof GeminiModelEnum) {
			throw new GeminiClientException('Gemini client requires a Gemini model.');
		}

		$this->logger->info('Sending Gemini generateContent request', [
			'model' => $model->value,
			'promptLength' => strlen($prompt),
		]);

		try {
			$response = $this->psr18ClientFactory->getClient()->sendRequest(
				$this->psr7RequestFactory->createPOSTRequest(
					sprintf(self::API_URL_TEMPLATE, rawurlencode($model->value)),
					$this->createPayload($prompt, $systemInstruction, $responseSchema),
					[
						'Content-Type' => 'application/json',
						'x-goog-api-key' => $this->apiKey,
					],
				),
			);
		} catch (ClientExceptionInterface $exception) {
			$this->logger->error('Gemini generateContent HTTP request failed', [
				'model' => $model->value,
				'exception' => $exception,
			]);

			throw new GeminiClientException('Gemini generateContent HTTP request failed.', previous: $exception);
		}

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			$this->logger->error('Gemini generateContent returned non-success status code', [
				'model' => $model->value,
				'statusCode' => $response->getStatusCode(),
				'message' => $response->getBody()->getContents(),
			]);

			throw new GeminiClientException(sprintf(
				'Gemini generateContent returned status code %d.',
				$response->getStatusCode(),
			));
		}

		try {
			$responseData = TypeValidator::validateArray(Json::decode($response->getBody()->getContents(), true));
			$text = $this->extractText($responseData);
		} catch (GeminiClientException $exception) {
			$this->logger->error('Gemini generateContent response has invalid shape', [
				'model' => $model->value,
				'exception' => $exception,
			]);

			throw $exception;
		} catch (JsonException $exception) {
			$this->logger->error('Gemini generateContent response is not valid JSON', [
				'model' => $model->value,
				'exception' => $exception,
			]);

			throw new GeminiClientException('Gemini generateContent response is not valid JSON.', previous: $exception);
		} catch (Throwable $exception) {
			$this->logger->error('Gemini generateContent response has invalid shape', [
				'model' => $model->value,
				'exception' => $exception,
			]);

			throw new GeminiClientException('Gemini generateContent response has invalid shape.', previous: $exception);
		}

		$this->logger->info('Gemini generateContent request finished', [
			'model' => $model->value,
			'responseLength' => strlen($text),
		]);

		return $text;
	}

	/**
	 * @param array<string, mixed>|null $responseSchema
	 * @return array<string, mixed>
	 */
	private function createPayload(string $prompt, string|null $systemInstruction, array|null $responseSchema): array
	{
		$payload = [
			'contents' => [
				[
					'parts' => [
						[
							'text' => $prompt,
						],
					],
				],
			],
			'tools' => [
				[
					'google_search' => (object) [],
				],
			],
		];

		if ($responseSchema !== null) {
			$payload['generationConfig'] = [
				'responseMimeType' => 'application/json',
				'responseSchema' => $responseSchema,
			];
		}

		if ($systemInstruction !== null) {
			$payload['systemInstruction'] = [
				'parts' => [
					[
						'text' => $systemInstruction,
					],
				],
			];
		}

		return $payload;
	}

	/**
	 * @param array<mixed> $responseData
	 */
	private function extractText(array $responseData): string
	{
		$candidates = TypeValidator::validateArray($responseData['candidates'] ?? null);
		$firstCandidate = TypeValidator::validateArray($candidates[0] ?? null);
		$content = TypeValidator::validateArray($firstCandidate['content'] ?? null);
		$parts = TypeValidator::validateArray($content['parts'] ?? null);
		$textParts = [];

		foreach ($parts as $part) {
			$partData = TypeValidator::validateArray($part);
			if (array_key_exists('text', $partData)) {
				$textParts[] = TypeValidator::validateString($partData['text']);
			}
		}

		$text = implode('', $textParts);
		if ($text === '') {
			throw new GeminiClientException('Gemini generateContent response does not contain text.');
		}

		return $text;
	}

}
