<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

class StockAssetDividendForecastCashflowMonth
{

	/** @var array<StockAssetDividendForecastCashflowItem> */
	private array $items = [];

	private float $netAmountInCzk = 0.0;

	private float $grossAmountInCzk = 0.0;

	private int $confirmedItemsCount = 0;

	private int $estimatedItemsCount = 0;

	public function __construct(private int $year, private int $month,)
	{
	}

	public function addItem(StockAssetDividendForecastCashflowItem $item): void
	{
		$this->items[] = $item;
		$this->netAmountInCzk += $item->getNetAmountInCzk();
		$this->grossAmountInCzk += $item->getGrossAmountInCzk();

		if ($item->isConfirmed()) {
			$this->confirmedItemsCount++;
		} else {
			$this->estimatedItemsCount++;
		}
	}

	public function getYear(): int
	{
		return $this->year;
	}

	public function getMonth(): int
	{
		return $this->month;
	}

	public function getLabel(): string
	{
		return sprintf('%02d/%d', $this->month, $this->year);
	}

	/**
	 * @return array<StockAssetDividendForecastCashflowItem>
	 */
	public function getItems(): array
	{
		return $this->items;
	}

	public function getNetAmountInCzk(): float
	{
		return $this->netAmountInCzk;
	}

	public function getGrossAmountInCzk(): float
	{
		return $this->grossAmountInCzk;
	}

	public function getConfirmedItemsCount(): int
	{
		return $this->confirmedItemsCount;
	}

	public function getEstimatedItemsCount(): int
	{
		return $this->estimatedItemsCount;
	}

	public function hasItems(): bool
	{
		return $this->items !== [];
	}

}
