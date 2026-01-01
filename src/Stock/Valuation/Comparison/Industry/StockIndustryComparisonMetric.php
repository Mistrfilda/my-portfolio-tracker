<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Comparison\Industry;

use App\Stock\Valuation\StockValuationTypeEnum;
use App\UI\Tailwind\TailwindColorConstant;

class StockIndustryComparisonMetric
{

	public function __construct(
		private StockValuationTypeEnum $metric,
		private float $stockValue,
		private float|null $industryAverage,
		private StockIndustryComparisonState $state,
	)
	{
	}

	public function getMetric(): StockValuationTypeEnum
	{
		return $this->metric;
	}

	public function getStockValue(): float
	{
		return $this->stockValue;
	}

	public function getIndustryAverage(): float|null
	{
		return $this->industryAverage;
	}

	public function getState(): StockIndustryComparisonState
	{
		return $this->state;
	}

	public function getDeviationPercentage(): float|null
	{
		if ($this->industryAverage === null || $this->industryAverage === 0.0) {
			return null;
		}

		return ($this->stockValue - $this->industryAverage) / $this->industryAverage * 100;
	}

	public function hasIndustryData(): bool
	{
		return $this->industryAverage !== null;
	}

	public function isPositive(): bool
	{
		return $this->state->isPositive($this->metric);
	}

	public function getColorClass(): string
	{
		if (!$this->hasIndustryData()) {
			return TailwindColorConstant::GRAY;
		}

		if ($this->state === StockIndustryComparisonState::IN_LINE) {
			return TailwindColorConstant::ORANGE;
		}

		return $this->isPositive() ? TailwindColorConstant::GREEN : TailwindColorConstant::RED;
	}

	public function getBorderColorClass(): string
	{
		$color = $this->getColorClass();
		return "border-{$color}-200";
	}

	public function getBgColorClass(): string
	{
		$color = $this->getColorClass();
		return "bg-{$color}-50";
	}

	public function getTextColorClass(): string
	{
		$color = $this->getColorClass();
		return "text-{$color}-600";
	}

	public function getRingColorClass(): string
	{
		$color = $this->getColorClass();
		return "ring-{$color}-600/20";
	}

	public function getBadgeClasses(): string
	{
		$color = $this->getColorClass();
		return "bg-{$color}-50 text-{$color}-700 ring-1 ring-inset ring-{$color}-600/20";
	}

	public function getIconBackgroundColor(): string
	{
		$color = $this->getColorClass();
		return "bg-{$color}-50";
	}

	public function getIconColor(): string
	{
		$color = $this->getColorClass();
		return "text-{$color}-600";
	}

	public function getDeviationTextColor(): string
	{
		if ($this->state === StockIndustryComparisonState::IN_LINE) {
			return 'text-orange-600';
		}

		return $this->isPositive() ? 'text-green-600' : 'text-red-600';
	}

	public function getDeviationArrowColor(): string
	{
		return $this->getDeviationTextColor();
	}

}
