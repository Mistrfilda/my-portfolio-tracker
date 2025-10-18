<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

class PortfolioStatisticTotalGroup
{

	/** @var array<PortfolioStatisticTotalValue> */
	private array $values;

	private PortfolioStatisticTotalValue $yearValue;

	public function __construct(private int $year)
	{
	}

	public function addValue(PortfolioStatisticTotalValue $portfolioStatisticTotalValue): void
	{
		$this->values[] = $portfolioStatisticTotalValue;
	}

	public function setYearValue(PortfolioStatisticTotalValue $yearValue): void
	{
		$this->yearValue = $yearValue;
	}

	public function getYear(): int
	{
		return $this->year;
	}

	/**
	 * @return array<PortfolioStatisticTotalValue>
	 */
	public function getValues(): array
	{
		return array_reverse($this->values);
	}

	public function getYearValue(): PortfolioStatisticTotalValue
	{
		return $this->yearValue;
	}

}
