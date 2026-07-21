<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;
use const DATE_ATOM;

class StockAiAnalysisV2SnapshotFactory
{

	public function __construct(private readonly StockAiAnalysisPromptGenerator $legacyPromptGenerator)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function create(
		UuidInterface $runId,
		ImmutableDateTime $analysisAsOf,
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
		string|null $stockTicker,
		string|null $stockName,
		StockAsset|null $stockAsset,
	): array
	{
		$allPortfolioData = $includesPortfolio || $includesWatchlist
			? $this->legacyPromptGenerator->getAutomaticPortfolioData()
			: [];
		$portfolioData = $includesPortfolio ? $allPortfolioData : [];
		$watchlistData = $includesWatchlist
			? $this->legacyPromptGenerator->getAutomaticWatchlistData()
			: [];

		$sectorAllocation = [];
		$portfolioContextPositions = [];
		foreach ($allPortfolioData as $item) {
			if (!is_array($item)) {
				continue;
			}

			$sector = is_string($item['sector'] ?? null) ? $item['sector'] : 'Unknown';
			$weight = is_float($item['portfolioPercentage'] ?? null)
				|| is_int($item['portfolioPercentage'] ?? null)
				? (float) $item['portfolioPercentage']
				: 0.0;
			$sectorAllocation[$sector] = round(($sectorAllocation[$sector] ?? 0.0) + $weight, 2);
			$portfolioContextPositions[] = [
				'stockAssetId' => $item['stockAssetId'] ?? null,
				'stockAssetTicker' => $item['stockAssetTicker'] ?? null,
				'sector' => $item['sector'] ?? null,
				'portfolioPercentage' => $weight,
				'contextOnly' => !$includesPortfolio,
			];
		}

		arsort($sectorAllocation);

		$singleStockData = null;
		if ($stockTicker !== null && $stockName !== null) {
			$singleStockData = [
				'stockAssetId' => $stockAsset?->getId()->toString(),
				'stockAssetTicker' => $stockTicker,
				'stockAssetName' => $stockName,
				'currency' => $stockAsset?->getCurrency()->value,
				'sector' => $stockAsset?->getIndustry()?->getName(),
				'currentPrice' => $stockAsset?->getAssetCurrentPrice()->getPrice(),
			];
		}

		return [
			'schemaVersion' => 2,
			'runId' => $runId->toString(),
			'analysisAsOf' => $analysisAsOf->format(DATE_ATOM),
			'timezone' => 'Europe/Prague',
			'scope' => [
				'includesPortfolio' => $includesPortfolio,
				'includesWatchlist' => $includesWatchlist,
				'includesMarketOverview' => $includesMarketOverview,
				'portfolioPromptType' => $portfolioPromptType?->value,
				'includesStockAnalysis' => $singleStockData !== null,
			],
			'conventions' => [
				'percentageUnit' => 'percentage_points',
				'priceUnit' => 'major_currency_unit',
				'currencyValues' => ['USD', 'EUR', 'CZK', 'GBP', 'PLN', 'NOK'],
				'narrativeLanguage' => 'cs',
				'jsonKeyLanguage' => 'en',
			],
			'portfolio' => array_values($portfolioData),
			'watchlist' => array_values($watchlistData),
			'portfolioContext' => [
				'totalPositions' => count($allPortfolioData),
				'sectorAllocation' => $sectorAllocation,
				'positions' => $portfolioContextPositions,
			],
			'singleStock' => $singleStockData,
		];
	}

}
