<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model\UI\Control;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\UI\Control\StockValuationModelTableControlItem;
use PHPUnit\Framework\TestCase;

class StockValuationModelTableControlItemTest extends TestCase
{

	public function testReturnsModelSummaryFromCalculatedResponses(): void
	{
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice($stockAsset, 100.0, CurrencyEnum::USD),
		);

		$item = new StockValuationModelTableControlItem(
			$stockAsset,
			[
				$this->createResponse($stockAsset, 110.0, 20.0, StockValuationModelState::UNDERPRICED),
				$this->createResponse($stockAsset, 130.0, -10.0, StockValuationModelState::OVERPRICED),
				$this->createResponse($stockAsset, null, null, StockValuationModelState::UNABLE_TO_CALCULATE),
			],
		);

		self::assertSame(2, $item->getCalculatedModelsCount());
		self::assertSame(3, $item->getModelsCount());
		self::assertSame(120.0, $item->getAverageModelPrice()?->getPrice());
		self::assertSame(5.0, $item->getAveragePercentage());
		self::assertSame(-10.0, $item->getMinimumPercentage());
		self::assertSame(20.0, $item->getMaximumPercentage());
	}

	public function testReturnsNullSummaryWhenNoModelCanBeCalculated(): void
	{
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice($stockAsset, 100.0, CurrencyEnum::USD),
		);

		$item = new StockValuationModelTableControlItem(
			$stockAsset,
			[$this->createResponse($stockAsset, null, null, StockValuationModelState::UNABLE_TO_CALCULATE)],
		);

		self::assertSame(0, $item->getCalculatedModelsCount());
		self::assertNull($item->getAverageModelPrice());
		self::assertNull($item->getAveragePercentage());
		self::assertNull($item->getMinimumPercentage());
		self::assertNull($item->getMaximumPercentage());
	}

	private function createResponse(
		StockAsset $stockAsset,
		float|null $price,
		float|null $percentage,
		StockValuationModelState $state,
	): StockValuationModelResponse
	{
		$response = $this->createStub(StockValuationModelResponse::class);
		$response->method('getAssetPrice')->willReturn(
			$price === null ? null : new AssetPrice($stockAsset, $price, CurrencyEnum::USD),
		);
		$response->method('getCalculatedPercentage')->willReturn($percentage);
		$response->method('getStockValuationModelTrend')->willReturn($state);

		return $response;
	}

}
