<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model\UI\Control;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;
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
		$consensus = $this->createStub(StockValuationModelConsensus::class);
		$consensus->method('getValidModelsCount')->willReturn(2);
		$consensus->method('getTotalModelsCount')->willReturn(3);
		$consensus->method('getPrice')->willReturn(new AssetPrice($stockAsset, 120.0, CurrencyEnum::USD));

		$item = new StockValuationModelTableControlItem(
			$stockAsset,
			[
				$this->createResponse($stockAsset, 110.0, 20.0, StockValuationModelState::UNDERPRICED),
				$this->createResponse($stockAsset, 130.0, -10.0, StockValuationModelState::OVERPRICED),
				$this->createResponse($stockAsset, null, null, StockValuationModelState::UNABLE_TO_CALCULATE),
			],
			$consensus,
		);

		self::assertSame(2, $item->getCalculatedModelsCount());
		self::assertSame(3, $item->getModelsCount());
		self::assertSame(120.0, $item->getConsensusPrice()?->getPrice());
		self::assertSame($consensus, $item->getModelConsensus());
	}

	public function testReturnsNullSummaryWhenNoModelCanBeCalculated(): void
	{
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice($stockAsset, 100.0, CurrencyEnum::USD),
		);
		$consensus = $this->createStub(StockValuationModelConsensus::class);
		$consensus->method('getValidModelsCount')->willReturn(0);
		$consensus->method('getTotalModelsCount')->willReturn(1);
		$consensus->method('getPrice')->willReturn(null);

		$item = new StockValuationModelTableControlItem(
			$stockAsset,
			[$this->createResponse($stockAsset, null, null, StockValuationModelState::UNABLE_TO_CALCULATE)],
			$consensus,
		);

		self::assertSame(0, $item->getCalculatedModelsCount());
		self::assertNull($item->getConsensusPrice());
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
