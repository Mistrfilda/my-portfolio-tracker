<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPeriodStatisticDividendDTO
{

	/**
	 * @param array<string> $warnings
	 */
	public function __construct(
		public string $recordId,
		public string $stockAssetId,
		public string $stockAssetName,
		public string $ticker,
		public string $exDate,
		public string|null $paymentDate,
		public string $type,
		public int $pieces,
		public string $currency,
		public float $grossAmount,
		public float $netAmount,
		public float|null $grossAmountCzk,
		public float|null $netAmountCzk,
		public array $warnings = [],
	)
	{
	}

	public function getExDate(): ImmutableDateTime
	{
		return new ImmutableDateTime($this->exDate);
	}

	public function getPaymentDate(): ImmutableDateTime|null
	{
		return $this->paymentDate !== null ? new ImmutableDateTime($this->paymentDate) : null;
	}

}
