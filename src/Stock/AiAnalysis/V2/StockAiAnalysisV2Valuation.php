<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

final readonly class StockAiAnalysisV2Valuation
{

	public function __construct(
		public string $assessment,
		public float|null $fairValueLow,
		public float|null $fairValueBase,
		public float|null $fairValueHigh,
		public string|null $currency,
		public string|null $method,
		public string $summary,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'assessment' => $this->assessment,
			'fairValueLow' => $this->fairValueLow,
			'fairValueBase' => $this->fairValueBase,
			'fairValueHigh' => $this->fairValueHigh,
			'currency' => $this->currency,
			'method' => $this->method,
			'summary' => $this->summary,
		];
	}

}
