<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

class StockValuationDataAnalyticsParser
{

	/**
	 * @return array{low: string|null, average: string|null, current: string|null, high: string|null}|null
	 */
	public function parseAnalystPriceTargets(string $textContent): array|null
	{
		$values = [
			'low' => null,
			'average' => null,
			'current' => null,
			'high' => null,
		];

		$patterns = [
			'low' => '/(\d[\d,\.]+)\s+Low/i',
			'average' => '/(\d[\d,\.]+)\s+Average/i',
			'current' => '/(\d[\d,\.]+)\s+Current/i',
			'high' => '/(\d[\d,\.]+)\s+High/i',
		];

		foreach ($patterns as $key => $pattern) {
			if (preg_match($pattern, $textContent, $matches) === 1) {
				$values[$key] = $matches[1];
			}
		}

		if ($values['low'] === null && $values['average'] === null &&
			$values['current'] === null && $values['high'] === null) {
			return null;
		}

		return $values;
	}

}
