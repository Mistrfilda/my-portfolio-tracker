<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset;

use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Test\UpdatedTestCase;
use Mockery;

class StockAssetDetailDTOTest extends UpdatedTestCase
{

	public function testGetAveragePurchasePrice(): void
	{
		$dto = $this->createStockAssetDetailDTO(
			new SummaryPrice(CurrencyEnum::USD, 250),
			5,
		);

		self::assertSame(50.0, $dto->getAveragePurchasePrice());
	}

	public function testGetAveragePurchasePriceWithoutPieces(): void
	{
		$dto = $this->createStockAssetDetailDTO(
			new SummaryPrice(CurrencyEnum::USD, 250),
			0,
		);

		self::assertSame(0.0, $dto->getAveragePurchasePrice());
	}

	private function createStockAssetDetailDTO(
		SummaryPrice $totalInvestedAmount,
		int $piecesCount,
	): StockAssetDetailDTO
	{
		return new StockAssetDetailDTO(
			Mockery::mock(StockAsset::class),
			[],
			$totalInvestedAmount,
			new SummaryPrice(CurrencyEnum::USD),
			new SummaryPrice(CurrencyEnum::USD),
			new SummaryPrice(CurrencyEnum::USD),
			new PriceDiff(0, 0, CurrencyEnum::USD),
			new PriceDiff(0, 0, CurrencyEnum::USD),
			new SummaryPrice(CurrencyEnum::CZK),
			new PriceDiff(0, 0, CurrencyEnum::CZK),
			$piecesCount,
		);
	}

}
