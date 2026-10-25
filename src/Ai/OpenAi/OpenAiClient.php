<?php

declare(strict_types = 1);

namespace App\Ai\OpenAi;

use App\Ai\AiClient;
use App\Ai\AiModel;
use App\Ai\DefaultAiModelEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Utils\TypeValidator;
use InvalidArgumentException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;

class OpenAiClient implements AiClient
{

	private const API_URL = 'https://api.openai.com/v1/responses';

	public function __construct(
		private readonly string $apiKey,
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly LoggerInterface $logger,
	)
	{
	}

	/**
	 * @param array<string, mixed>|null $responseSchema JSON Schema compatible with OpenAI strict Structured Outputs.
	 */
	public function generateContent(
		string $prompt,
		string|null $systemInstruction = null,
		array|null $responseSchema = null,
		AiModel $model = DefaultAiModelEnum::DEFAULT,
	): string
	{
		if ($model === DefaultAiModelEnum::DEFAULT) {
			$model = OpenAiModelEnum::DEFAULT;
		}

		if (!$model instanceof OpenAiModelEnum) {
			throw new OpenAiClientException('OpenAI client requires an OpenAI model.');
		}

		$this->logger->info('Sending OpenAI response request', [
			'model' => $model->value,
			'promptLength' => strlen($prompt),
		]);

		try {
			$response = $this->psr18ClientFactory->getClient()->sendRequest(
				$this->psr7RequestFactory->createPOSTRequest(
					self::API_URL,
					$this->createPayload($prompt, $systemInstruction, $responseSchema, $model),
					[
						'Content-Type' => 'application/json',
						'Authorization' => 'Bearer ' . $this->apiKey,
					],
				),
			);
		} catch (ClientExceptionInterface $exception) {
			$this->logger->error('OpenAI response HTTP request failed', ['model' => $model->value]);

			throw new OpenAiClientException('OpenAI response HTTP request failed.', previous: $exception);
		} catch (JsonException $exception) {
			throw new OpenAiClientException('OpenAI response request is not valid JSON.', previous: $exception);
		}

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			$this->logger->error('OpenAI response returned non-success status code', [
				'model' => $model->value,
				'statusCode' => $response->getStatusCode(),
			]);

			throw new OpenAiClientException(sprintf(
				'OpenAI response returned status code %d.',
				$response->getStatusCode(),
			));
		}

		try {
			$responseData = TypeValidator::validateArray(Json::decode($response->getBody()->getContents(), true));
			$text = $this->extractText($responseData);
		} catch (OpenAiClientException $exception) {
			$this->logger->error(
				'OpenAI response does not contain a completed text result',
				['model' => $model->value],
			);

			throw $exception;
		} catch (JsonException $exception) {
			$this->logger->error('OpenAI response is not valid JSON', ['model' => $model->value]);

			throw new OpenAiClientException('OpenAI response is not valid JSON.', previous: $exception);
		} catch (InvalidArgumentException $exception) {
			$this->logger->error('OpenAI response has invalid shape', ['model' => $model->value]);

			throw new OpenAiClientException('OpenAI response has invalid shape.', previous: $exception);
		}

		$this->logger->info('OpenAI response request finished', [
			'model' => $model->value,
			'responseLength' => strlen($text),
		]);

		return $text;
	}

	/**
	 * @param array<string, mixed>|null $responseSchema
	 * @return array<string, mixed>
	 */
	private function createPayload(
		string $prompt,
		string|null $systemInstruction,
		array|null $responseSchema,
		OpenAiModelEnum $model,
	): array
	{
		$payload = [
			'model' => $model->value,
			'input' => $prompt,
			'store' => false,
		];

		if ($systemInstruction !== null) {
			$payload['instructions'] = $systemInstruction;
		}

		if ($responseSchema !== null) {
			$payload['text'] = [
				'format' => [
					'type' => 'json_schema',
					'name' => 'response',
					'strict' => true,
					'schema' => $responseSchema,
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
		$status = TypeValidator::validateString($responseData['status'] ?? null);
		if ($status !== 'completed') {
			throw new OpenAiClientException('OpenAI response did not complete successfully.');
		}

		$output = TypeValidator::validateArray($responseData['output'] ?? null);
		$textParts = [];
		foreach ($output as $item) {
			$itemData = TypeValidator::validateArray($item);
			if (TypeValidator::validateString($itemData['type'] ?? null) !== 'message') {
				continue;
			}

			$content = TypeValidator::validateArray($itemData['content'] ?? null);
			foreach ($content as $part) {
				$partData = TypeValidator::validateArray($part);
				$type = TypeValidator::validateString($partData['type'] ?? null);
				if ($type === 'refusal') {
					throw new OpenAiClientException('OpenAI response was refused.');
				}

				if ($type === 'output_text') {
					$textParts[] = TypeValidator::validateString($partData['text'] ?? null);
				}
			}
		}

		$text = implode('', $textParts);
		if (trim($text) === '') {
			throw new OpenAiClientException('OpenAI response does not contain text.');
		}

		return $text;
	}

}
