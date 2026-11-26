<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyProvider;
use App\Stock\Valuation\StockValuationPriceProvider;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class WatchlistTool
{

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockAssetWatchlistRepository $watchlistRepository,
		private readonly StockValuationPriceProvider $valuationPriceProvider,
		private readonly StockValuationMarginOfSafetyProvider $marginOfSafetyProvider,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(bool $includeSimpleWatchlist = true): array
	{
		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();

		$fullWatchlist = [];
		foreach ($this->stockAssetRepository->findAllWatchlist() as $asset) {
			$consensus = $this->valuationPriceProvider->getModelConsensus($asset);
			$analyticsPrice = $this->valuationPriceProvider->getAnalyticsPrice($asset);
			$aiPrice = $this->valuationPriceProvider->getAiAnalysisPrice($asset, $owner);
			$marginOfSafety = $this->marginOfSafetyProvider->getForStockAsset(
				$asset,
				$consensus,
				$analyticsPrice,
				$aiPrice,
			);

			$fullWatchlist[] = [
				'stockAssetId' => $asset->getId()->toString(),
				'ticker' => $asset->getTicker(),
				'name' => $asset->getName(),
				'sector' => $asset->getIndustry()?->getName(),
				'currency' => $asset->getCurrency()->value,
				'currentPrice' => round($asset->getAssetCurrentPrice()->getPrice(), 4),
				'priceDownloadedAt' => $asset->getPriceDownloadedAt()?->format(DATE_ATOM),
				'valuationDownloadedAt' => $asset->getValuationDownloadedAt()?->format(DATE_ATOM),
				'modelConsensus' => [
					'fairPrice' => $consensus->getPrice() !== null ? round(
						$consensus->getPrice()->getPrice(),
						4,
					) : null,
					'upsidePercent' => $consensus->getPercentage() !== null ? round(
						$consensus->getPercentage(),
						2,
					) : null,
					'confidence' => $consensus->getConfidence()->value,
					'validModelsCount' => $consensus->getValidModelsCount(),
					'totalModelsCount' => $consensus->getTotalModelsCount(),
				],
				'analyticsTargetPrice' => $analyticsPrice !== null ? round($analyticsPrice->getPrice(), 4) : null,
				'aiTargetPrice' => $aiPrice !== null ? round($aiPrice->getPrice(), 4) : null,
				'marginOfSafety' => [
					'fairPriceEstimate' => $marginOfSafety->getFairPriceEstimate() !== null
						? round($marginOfSafety->getFairPriceEstimate()->getPrice(), 4)
						: null,
					'marginPercentage' => $marginOfSafety->getMarginPercentage() !== null
						? round($marginOfSafety->getMarginPercentage(), 2)
						: null,
					'status' => $marginOfSafety->getStatus()->value,
					'confidence' => $marginOfSafety->getConfidence()->value,
				],
			];
		}

		$simpleWatchlist = [];
		if ($includeSimpleWatchlist) {
			foreach ($this->watchlistRepository->findAllForAppAdmin($owner) as $item) {
				$simpleWatchlist[] = [
					'id' => $item->getId()->toString(),
					'ticker' => $item->getTicker(),
					'name' => $item->getName(),
					'currency' => $item->getCurrency()?->value,
					'recommendedEntryPrice' => $item->getRecommendedEntryPrice(),
					'updatedAt' => $item->getUpdatedAt()->format(DATE_ATOM),
				];
			}
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'fullWatchlistCount' => count($fullWatchlist),
			'fullWatchlist' => $fullWatchlist,
			'simpleWatchlistCount' => count($simpleWatchlist),
			'simpleWatchlist' => $simpleWatchlist,
		];
	}

}
