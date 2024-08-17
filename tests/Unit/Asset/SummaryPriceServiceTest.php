<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\Exception\PriceDiffException;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyEnum;
use App\Test\Unit\Currency\CurrencyConversionHelper;
use App\Test\UpdatedTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class SummaryPriceServiceTest extends UpdatedTestCase
{

	private SummaryPriceService $summaryPriceService;

	public function testGetSummaryPriceDiff(): void
	{
		$summaryPrice1 = new SummaryPrice(CurrencyEnum::CZK, 2000.50);

		$summaryPrice2 = new SummaryPrice(CurrencyEnum::CZK, 1000.75);

		$priceDiff = $this->summaryPriceService->getSummaryPriceDiff(
			$summaryPrice1,
			$summaryPrice2,
		);

		self::assertEquals(
			new PriceDiff(999.75, 199.90007494379216, CurrencyEnum::CZK),
			$priceDiff,
		);

		self::assertException(function () use ($summaryPrice1): void {
			$this->summaryPriceService->getSummaryPriceDiff(
				$summaryPrice1,
				new SummaryPrice(
					CurrencyEnum::EUR,
					500,
				),
			);
		}, PriceDiffException::class, 'Currency must be same');
	}

	#[DataProvider('provideSummaryPricePositions')]

	/**
	 * @param array<AssetPosition> $positions
	 */
	public function testGetSummaryPriceForPositions(
		SummaryPrice $expectedSummaryPrice,
		array $positions,
	): void
	{
		self::assertEquals(
			$expectedSummaryPrice,
			$this->summaryPriceService->getSummaryPriceForPositions(
				$expectedSummaryPrice->getCurrency(),
				$positions,
			),
		);
	}

	#[DataProvider('provideSummaryPriceForTotalInvestedAmmountPositions')]

	/**
	 * @param array<AssetPosition> $positions
	 */
	public function testGetSummaryPriceForTotalInvestedAmount(
		SummaryPrice $expectedSummaryPrice,
		array $positions,
	): void
	{
		self::assertEquals(
			$expectedSummaryPrice,
			$this->summaryPriceService->getSummaryPriceForTotalInvestedAmount(
				$expectedSummaryPrice->getCurrency(),
				$positions,
			),
		);
	}

	#[DataProvider('provideSummaryPriceForTotalInvestedAmmountInBrokerCurrencyPositions')]

	/**
	 * @param array<AssetPosition> $positions
	 */
	public function testGetSummaryPriceForTotalInvestedAmountInBrokerCurrency(
		SummaryPrice $expectedSummaryPrice,
		array $positions,
	): void
	{
		self::assertEquals(
			$expectedSummaryPrice,
			$this->summaryPriceService->getSummaryPriceForTotalInvestedAmountInBrokerCurrency(
				$expectedSummaryPrice->getCurrency(),
				$positions,
			),
		);
	}

	/**
	 * @return array<string, array<mixed>>
	 */
	public static function provideSummaryPricePositions(): array
	{
		$positions = self::getPositions();

		return [
			'czk' => [
				new SummaryPrice(CurrencyEnum::CZK, 423.76, 2),
				$positions[0],
			],
		];
	}

	/**
	 * @return array<string, array<mixed>>
	 */
	public static function provideSummaryPriceForTotalInvestedAmmountPositions(): array
	{
		$positions = self::getPositions();

		return [
			'czk' => [
				new SummaryPrice(CurrencyEnum::CZK, 628.3299999999999, 2),
				$positions[0],
			],
		];
	}

	/**
	 * @return array<string, array<mixed>>
	 */
	public static function provideSummaryPriceForTotalInvestedAmmountInBrokerCurrencyPositions(): array
	{
		$positions = self::getPositions();

		return [
			'czk' => [
				new SummaryPrice(CurrencyEnum::CZK, 485.08, 2),
				$positions[0],
			],
		];
	}

	/**
	 * @return array<int, array<int, Mockery\MockInterface>>
	 */
	private static function getPositions(): array
	{
		$asset = Mockery::mock(Asset::class)->makePartial();

		return [
			[
				Mockery::mock(AssetPosition::class)
					->shouldReceive([
						'getCurrentTotalAmount' => new AssetPrice(
							$asset,
							100.50,
							CurrencyEnum::CZK,
						),
						'getTotalInvestedAmount' => new AssetPrice(
							$asset,
							200.50,
							CurrencyEnum::CZK,
						),
						'getTotalInvestedAmountInBrokerCurrency' => new AssetPrice(
							$asset,
							200.50,
							CurrencyEnum::CZK,
						),
					])->getMock(),
				Mockery::mock(AssetPosition::class)
					->shouldReceive([
						'getCurrentTotalAmount' => new AssetPrice(
							$asset,
							323.26,
							CurrencyEnum::CZK,
						),
						'getTotalInvestedAmount' => new AssetPrice(
							$asset,
							427.83,
							CurrencyEnum::CZK,
						),
						'getTotalInvestedAmountInBrokerCurrency' => new AssetPrice(
							$asset,
							284.58,
							CurrencyEnum::CZK,
						),
					])->getMock(),
			],
		];
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);
	}

}
