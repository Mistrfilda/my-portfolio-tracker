<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Total\UI\Control;

class PortfolioStatisticTotalValue
{

	public function __construct(
		private int|null $month,
		private string $label,
		private float $investedAtStart,
		private float $investedAtEnd,
		private float $valueAtStart,
		private float $valueAtEnd,
	)
	{
	}

	public function getMonth(): int|null
	{
		return $this->month;
	}

	public function getInvestedAtStart(): float
	{
		return $this->investedAtStart;
	}

	public function getInvestedAtEnd(): float
	{
		return $this->investedAtEnd;
	}

	public function getValueAtStart(): float
	{
		return $this->valueAtStart;
	}

	public function getValueAtEnd(): float
	{
		return $this->valueAtEnd;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getDiffAmount(): float
	{
		$investedDiff = $this->investedAtEnd - $this->investedAtStart;
		$valueDiff = $this->valueAtEnd - $this->valueAtStart;
		return $valueDiff - $investedDiff;
	}

}
