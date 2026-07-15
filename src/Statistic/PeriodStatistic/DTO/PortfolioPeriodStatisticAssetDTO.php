<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPeriodStatisticAssetDTO
{

	/**
	 * @param array<string> $warnings
	 */
	public function __construct(
		public string $assetId,
		public string $assetType,
		public string $name,
		public string|null $ticker,
		public string $currency,
		public string|null $priceStartDate,
		public string|null $priceEndDate,
		public float|null $priceAtStart,
		public float|null $priceAtEnd,
		public float|null $marketPerformancePercentage,
		public float|null $valueAtStartCzk,
		public float|null $valueAtEndCzk,
		public float $purchasesCzk,
		public float $salesCzk,
		public float|null $capitalResultCzk,
		public float $netDividendsCzk,
		public float|null $totalContributionCzk,
		public array $warnings = [],
	)
	{
	}

	public function getPriceStartDate(): ImmutableDateTime|null
	{
		return $this->priceStartDate !== null ? new ImmutableDateTime($this->priceStartDate) : null;
	}

	public function getPriceEndDate(): ImmutableDateTime|null
	{
		return $this->priceEndDate !== null ? new ImmutableDateTime($this->priceEndDate) : null;
	}

}
