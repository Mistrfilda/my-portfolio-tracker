<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\Exception\SummaryPriceException;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Income\Bank\BankIncome;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Currency\CurrencyEnum;
use PHPUnit\Framework\TestCase;

class SummaryPriceTest extends TestCase
{

	public function testAddsAllSupportedPriceSourcesAndCounters(): void
	{
		$asset = $this->createStub(Asset::class);
		$bankExpense = $this->createStub(BankExpense::class);
		$bankExpense->method('getCurrency')->willReturn(CurrencyEnum::CZK);
		$bankExpense->method('getAmount')->willReturn(-4.25);
		$bankIncome = $this->createStub(BankIncome::class);
		$bankIncome->method('getCurrency')->willReturn(CurrencyEnum::CZK);
		$bankIncome->method('getAmount')->willReturn(10.0);
		$workIncome = $this->createStub(WorkMonthlyIncome::class);
		$workIncome->method('getCurrencyEnum')->willReturn(CurrencyEnum::CZK);
		$workIncome->method('getSummaryPrice')->willReturn(new SummaryPrice(CurrencyEnum::CZK, 20.5, 4));

		$summary = new SummaryPrice(CurrencyEnum::CZK, 10.4, 1);
		$summary->addAssetPrice(new AssetPrice($asset, 5.1, CurrencyEnum::CZK));
		$summary->addSummaryPrice(new SummaryPrice(CurrencyEnum::CZK, 7.25, 3));
		$summary->addPriceDiff(new PriceDiff(3.5, 110.0, CurrencyEnum::CZK));
		$summary->addBankExpense($bankExpense);
		$summary->addBankIncome($bankIncome);
		$summary->addWorkMonthlyIncome($workIncome);
		$summary->addFlat(1.4, 2);

		self::assertSame(CurrencyEnum::CZK, $summary->getCurrency());
		self::assertSame(53.9, $summary->getPrice());
		self::assertSame(53, $summary->getRoundedPrice());
		self::assertSame(14, $summary->getCounter());
		self::assertTrue($summary->isFilled());
	}

	public function testEmptySummaryIsNotFilled(): void
	{
		$summary = new SummaryPrice(CurrencyEnum::EUR);

		self::assertSame(0.0, $summary->getPrice());
		self::assertSame(0, $summary->getCounter());
		self::assertFalse($summary->isFilled());
	}

	public function testRejectsDifferentCurrencyForEveryTypedInput(): void
	{
		$asset = $this->createStub(Asset::class);
		$bankExpense = $this->createStub(BankExpense::class);
		$bankExpense->method('getCurrency')->willReturn(CurrencyEnum::EUR);
		$bankIncome = $this->createStub(BankIncome::class);
		$bankIncome->method('getCurrency')->willReturn(CurrencyEnum::EUR);
		$workIncome = $this->createStub(WorkMonthlyIncome::class);
		$workIncome->method('getCurrencyEnum')->willReturn(CurrencyEnum::EUR);
		$summary = new SummaryPrice(CurrencyEnum::CZK);

		$this->assertCurrencyMismatch(
			static fn () => $summary->addAssetPrice(new AssetPrice($asset, 1.0, CurrencyEnum::EUR)),
		);
		$this->assertCurrencyMismatch(
			static fn () => $summary->addSummaryPrice(new SummaryPrice(CurrencyEnum::EUR, 1.0)),
		);
		$this->assertCurrencyMismatch(
			static fn () => $summary->addPriceDiff(new PriceDiff(1.0, 101.0, CurrencyEnum::EUR)),
		);
		$this->assertCurrencyMismatch(static fn () => $summary->addBankExpense($bankExpense));
		$this->assertCurrencyMismatch(static fn () => $summary->addBankIncome($bankIncome));
		$this->assertCurrencyMismatch(static fn () => $summary->addWorkMonthlyIncome($workIncome));
	}

	/**
	 * @param callable(): void $operation
	 */
	private function assertCurrencyMismatch(callable $operation): void
	{
		try {
			$operation();
			self::fail('SummaryPriceException was not thrown.');
		} catch (SummaryPriceException $exception) {
			self::assertSame(
				'Different currency EUR passed to summary - expected CZK',
				$exception->getMessage(),
			);
		}
	}

}
