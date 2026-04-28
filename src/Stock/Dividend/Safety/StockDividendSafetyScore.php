<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Safety;

class StockDividendSafetyScore
{

	/** @param array<string, string> $reasons */
	public function __construct(
		private int $score,
		private StockDividendSafetyScoreStatusEnum $status,
		private array $reasons,
	)
	{
	}

	public function getScore(): int
	{
		return $this->score;
	}

	public function getStatus(): StockDividendSafetyScoreStatusEnum
	{
		return $this->status;
	}

	/**
	 * @return array<string, string>
	 */
	public function getReasons(): array
	{
		return $this->reasons;
	}

}
