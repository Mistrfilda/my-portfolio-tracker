<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

final class StockAiAnalysisGeminiJsonNormalizer
{

	public function normalize(string $response): string
	{
		$response = trim($response);
		if (str_starts_with($response, '```')) {
			$response = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $response) ?? $response;
		}

		$response = $this->extractJsonObject($response);
		$response = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $response) ?? $response;

		return $this->repairKnownAnalysisPlaceholder($response);
	}

	private function extractJsonObject(string $response): string
	{
		$start = strpos($response, '{');
		$end = strrpos($response, '}');
		if ($start === false || $end === false || $end < $start) {
			return $response;
		}

		return substr($response, $start, $end - $start + 1);
	}

	private function repairKnownAnalysisPlaceholder(string $response): string
	{
		return preg_replace(
			'/("(?:(?:portfolio|watchlist)Analysis)"\s*:\s*)\.(\s*[\[{])/',
			'$1$2',
			$response,
		) ?? $response;
	}

}
