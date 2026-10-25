<?php

declare(strict_types = 1);

namespace App\Ai;

interface AiClient
{

	/**
	 * The optional response schema must use the selected provider's supported format.
	 * DEFAULT resolves to the provider's default model; models from another provider are rejected.
	 *
	 * @param array<string, mixed>|null $responseSchema
	 * @throws AiClientException
	 */
	public function generateContent(
		string $prompt,
		string|null $systemInstruction = null,
		array|null $responseSchema = null,
		AiModel $model = DefaultAiModelEnum::DEFAULT,
	): string;

}
