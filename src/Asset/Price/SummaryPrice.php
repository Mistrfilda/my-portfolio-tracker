<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Price\Exception\SummaryPriceException;
use App\Cash\Expense\Bank\BankExpense;
use App\Currency\CurrencyEnum;

class SummaryPrice
{

	private readonly CurrencyEnum $currency;

	private float $price;

	private int $counter;

	public function __construct(CurrencyEnum $currency, float $price = 0, int $counter = 0)
	{
		$this->currency = $currency;
		$this->price = $price;
		$this->counter = $counter;
	}

	public function addAssetPrice(AssetPrice $assetPrice): void
	{
		if ($assetPrice->getCurrency() !== $this->currency) {
			throw new SummaryPriceException(
				sprintf(
					'Different currency %s passed to summary - expected %s',
					$assetPrice->getCurrency()->format(),
					$this->currency->format(),
				),
			);
		}

		$this->price += $assetPrice->getPrice();
		$this->counter++;
	}

	public function addSummaryPrice(SummaryPrice $summaryPrice): void
	{
		if ($summaryPrice->getCurrency() !== $this->currency) {
			throw new SummaryPriceException(
				sprintf(
					'Different currency %s passed to summary - expected %s',
					$summaryPrice->getCurrency()->format(),
					$this->currency->format(),
				),
			);
		}

		$this->price += $summaryPrice->getPrice();
		$this->counter += $summaryPrice->getCounter();
	}

	public function addBankExpense(BankExpense $bankExpense): void
	{
		if ($bankExpense->getCurrency() !== $this->currency) {
			throw new SummaryPriceException(
				sprintf(
					'Different currency %s passed to summary - expected %s',
					$bankExpense->getCurrency()->format(),
					$this->currency->format(),
				),
			);
		}

		$this->price += $bankExpense->getAmount();
		$this->counter += 1;
	}

	public function addFlat(float $price, int $counter): void
	{
		$this->price += $price;
		$this->counter += $counter;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getRoundedPrice(): int
	{
		return (int) $this->price;
	}

	public function getPrice(): float
	{
		return $this->price;
	}

	public function getCounter(): int
	{
		return $this->counter;
	}

	public function isFilled(): bool
	{
		return $this->price !== 0.0;
	}

}
