<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;
use RuntimeException;

class StockAiAnalysisPromptGenerator
{

	private const string PROMPT_DIR = __DIR__ . '/prompt';

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockValuationDataRepository $stockValuationDataRepository,
		private AssetPriceSummaryFacade $assetPriceSummaryFacade,
		private StockPositionFacade $stockPositionFacade,
		private StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function generate(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType = null,
		string|null $stockTicker = null,
		string|null $stockName = null,
	): string
	{
		$now = $this->datetimeFactory->createNow();

		$parts = [];
		$parts[] = sprintf($this->loadPrompt('common/system'), $now->format('d. m. Y'));

		if ($includesMarketOverview) {
			$parts[] = $this->loadPrompt('common/market_overview');
		}

		$portfolioData = [];
		if ($includesPortfolio) {
			$portfolioData = $this->getPortfolioData();

			if ($portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
				$parts[] = $this->loadPrompt('portfolio/daily_portfolio');
				$parts[] = $this->loadPrompt('portfolio/daily_brief');
			} else {
				$parts[] = $this->loadPrompt('portfolio/portfolio');
				$parts[] = $this->loadPrompt('portfolio/portfolio_evaluation');
			}
		}

		$watchlistData = [];
		if ($includesWatchlist) {
			$watchlistData = $this->getWatchlistData();

			if ($portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
				$parts[] = $this->loadPrompt('portfolio/daily_watchlist');
			} else {
				$parts[] = $this->loadPrompt('portfolio/watchlist');
			}
		}

		if ($stockTicker !== null && $stockName !== null) {
			$parts[] = sprintf($this->loadPrompt('stock/stock_analysis'), $stockName, $stockTicker);
		}

		$parts[] = $this->loadPrompt('common/output_format');
		$parts[] = Json::encode(
			$this->buildJsonSchema(
				$includesPortfolio,
				$includesWatchlist,
				$includesMarketOverview,
				$portfolioPromptType,
				$stockTicker !== null && $stockName !== null,
			),
			pretty: true,
		);

		$parts[] = 'Data k analýze:';

		$data = [];
		if ($includesPortfolio) {
			$data['portfolio'] = $portfolioData;

			/** @var array<string, float> $sectorAllocation */
			$sectorAllocation = [];
			foreach ($portfolioData as $item) {
				assert(is_array($item));
				$sector = is_string($item['sector'] ?? null) ? $item['sector'] : 'Unknown';
				$portfolioPercentage = is_float(
					$item['portfolioPercentage'] ?? null,
				)
					? $item['portfolioPercentage']
					: 0.0;
				$sectorAllocation[$sector] = ($sectorAllocation[$sector] ?? 0) + $portfolioPercentage;
			}

			arsort($sectorAllocation);
			$data['sectorAllocation'] = $sectorAllocation;
			$data['totalPositions'] = count($portfolioData);
		}

		if ($includesWatchlist) {
			$data['watchlist'] = $watchlistData;
		}

		if ($data !== []) {
			$parts[] = Json::encode($data, pretty: true);
		}

		return implode("\n\n", $parts);
	}

	/**
	 * @return array<mixed>
	 */
	public function getAutomaticPortfolioData(): array
	{
		return $this->getPortfolioData();
	}

	/**
	 * @return array<mixed>
	 */
	public function getAutomaticWatchlistData(): array
	{
		return $this->getWatchlistData();
	}

	/**
	 * @param array<string, mixed> $portfolioItem
	 */
	public function generateAutomaticPortfolioStockPrompt(
		array $portfolioItem,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): string
	{
		$performanceCommentField = $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
			? 'performance1DayComment'
			: 'performance7DaysComment';

		return $this->generateAutomaticStockPrompt(
			'portfolioAnalysis',
			[
				[
					'stockAssetId' => 'uuid',
					'stockAssetName' => 'string',
					'stockAssetTicker' => 'string',
					'positiveNews' => 'string',
					'negativeNews' => 'string',
					'interestingNews' => 'string',
					'aiOpinion' => 'string',
					'earningsCommentary' => 'string',
					'dividendAnalysis' => 'string',
					$performanceCommentField => 'string',
					'actionSuggestion' => 'hold | consider_selling | add_more | watch_closely',
					'confidenceLevel' => 'low | medium | high',
					'fairPrice' => 'float',
					'fairPriceCurrency' => 'USD | EUR | CZK | ...',
				],
			],
			$portfolioItem,
		);
	}

	/**
	 * @param array<string, mixed> $watchlistItem
	 */
	public function generateAutomaticWatchlistStockPrompt(
		array $watchlistItem,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): string
	{
		$performanceCommentField = $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
			? 'performance1DayComment'
			: 'performance7DaysComment';

		return $this->generateAutomaticStockPrompt(
			'watchlistAnalysis',
			[
				[
					'stockAssetId' => 'uuid',
					'stockAssetName' => 'string',
					'stockAssetTicker' => 'string',
					'news' => 'string',
					'earningsCommentary' => 'string',
					'dividendAnalysis' => 'string',
					$performanceCommentField => 'string',
					'buyRecommendation' => 'consider_buying | wait | not_interesting',
					'reasoning' => 'string',
					'confidenceLevel' => 'low | medium | high',
					'fairPrice' => 'float',
					'fairPriceCurrency' => 'USD | EUR | CZK | ...',
				],
			],
			$watchlistItem,
		);
	}

	/**
	 * @param array<mixed> $portfolioAnalysis
	 * @param array<mixed> $watchlistAnalysis
	 */
	public function generateAutomaticReducePrompt(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
		array $portfolioAnalysis,
		array $watchlistAnalysis,
	): string
	{
		$now = $this->datetimeFactory->createNow();
		$schema = [];

		if ($includesMarketOverview) {
			$schema['marketOverview'] = [
				'summary' => 'string',
				'sentiment' => 'bullish | bearish | neutral',
				'geopoliticalContext' => 'string',
			];
		}

		if ($includesPortfolio && $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
			$schema['dailyBrief'] = [
				'summary' => 'string',
				'marketPulse' => 'string',
				'portfolioImpactSummary' => 'string',
				'watchlistSummary' => 'string',
				'importantAlerts' => 'string',
				'nextDaysChecklist' => 'string',
				'actionNeeded' => 'none | monitor | review_positions | review_watchlist',
			];
		} elseif ($includesPortfolio) {
			$schema['portfolioEvaluation'] = [
				'summary' => 'string',
				'performance7DaysSummary' => 'string',
			];
		}

		return implode("\n\n", [
			sprintf($this->loadPrompt('common/system'), $now->format('d. m. Y')),
			'Z dílčích analýz akcií vytvoř pouze souhrnné části JSON odpovědi podle níže uvedeného '
				. 'schématu. Neopisuj zpět pole portfolioAnalysis ani watchlistAnalysis.',
			$this->loadPrompt('common/output_format'),
			Json::encode($schema, pretty: true),
			'Dílčí analýzy:',
			Json::encode([
				'portfolioAnalysis' => $portfolioAnalysis,
				'watchlistAnalysis' => $watchlistAnalysis,
			], pretty: true),
		]);
	}

	/**
	 * @param array<mixed> $schema
	 * @param array<string, mixed> $stockData
	 */
	private function generateAutomaticStockPrompt(string $rootKey, array $schema, array $stockData): string
	{
		$now = $this->datetimeFactory->createNow();

		return implode("\n\n", [
			sprintf($this->loadPrompt('common/system'), $now->format('d. m. Y')),
			'Analyzuj pouze jednu níže uvedenou akcii. Použij aktuální webové informace přes Google Search a vrať pouze validní JSON podle schématu.',
			$this->loadPrompt('common/output_format'),
			Json::encode([
				$rootKey => $schema,
			], pretty: true),
			'Data k analýze:',
			Json::encode($stockData, pretty: true),
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonSchema(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
		bool $includesStockAnalysis,
	): array
	{
		$schema = [];

		if ($includesMarketOverview) {
			$schema['marketOverview'] = [
				'summary' => 'string',
				'sentiment' => 'bullish | bearish | neutral',
				'geopoliticalContext' => 'string',
			];
		}

		if ($includesPortfolio && $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
			$schema['dailyBrief'] = [
				'summary' => 'string',
				'marketPulse' => 'string',
				'portfolioImpactSummary' => 'string',
				'watchlistSummary' => 'string',
				'importantAlerts' => 'string',
				'nextDaysChecklist' => 'string',
				'actionNeeded' => 'none | monitor | review_positions | review_watchlist',
			];
		}

		if ($includesPortfolio && $portfolioPromptType !== StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
			$schema['portfolioEvaluation'] = [
				'summary' => 'string',
				'performance7DaysSummary' => 'string',
			];
		}

		if ($includesStockAnalysis) {
			$schema['stockAnalysis'] = [
				'businessSummary' => 'string',
				'moatAnalysis' => 'string',
				'financialHealth' => 'string',
				'earningsCommentary' => 'string',
				'growthCatalysts' => 'string',
				'risks' => 'string',
				'dividendAnalysis' => 'string',
				'valuationAssessment' => 'string',
				'conclusion' => 'string',
				'recommendation' => 'consider_buying | hold | consider_selling',
				'confidenceLevel' => 'low | medium | high',
				'fairPrice' => 'float',
				'fairPriceCurrency' => 'USD | EUR | CZK | ...',
			];
		}

		if ($includesPortfolio) {
			$performanceCommentField = $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
				? 'performance1DayComment'
				: 'performance7DaysComment';

			$schema['portfolioAnalysis'] = [
				[
					'stockAssetId' => 'uuid',
					'stockAssetName' => 'string',
					'stockAssetTicker' => 'string',
					'positiveNews' => 'string',
					'negativeNews' => 'string',
					'interestingNews' => 'string',
					'aiOpinion' => 'string',
					'earningsCommentary' => 'string',
					'dividendAnalysis' => 'string',
					$performanceCommentField => 'string',
					'actionSuggestion' => 'hold | consider_selling | add_more | watch_closely',
					'confidenceLevel' => 'low | medium | high',
					'fairPrice' => 'float',
					'fairPriceCurrency' => 'USD | EUR | CZK | ...',
				],
			];
		}

		if ($includesWatchlist) {
			$performanceCommentField = $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
				? 'performance1DayComment'
				: 'performance7DaysComment';

			$schema['watchlistAnalysis'] = [
				[
					'stockAssetId' => 'uuid',
					'stockAssetName' => 'string',
					'stockAssetTicker' => 'string',
					'news' => 'string',
					'earningsCommentary' => 'string',
					'dividendAnalysis' => 'string',
					$performanceCommentField => 'string',
					'buyRecommendation' => 'consider_buying | wait | not_interesting',
					'reasoning' => 'string',
					'confidenceLevel' => 'low | medium | high',
					'fairPrice' => 'float',
					'fairPriceCurrency' => 'USD | EUR | CZK | ...',
				],
			];
		}

		return $schema;
	}

	private function loadPrompt(string $name): string
	{
		$path = self::PROMPT_DIR . '/' . $name . '.txt';
		$content = file_get_contents($path);

		if ($content === false) {
			throw new RuntimeException(sprintf('Prompt file not found: %s', $path));
		}

		return trim($content);
	}

	/**
	 * @return array<mixed>
	 */
	private function getPortfolioData(): array
	{
		$assets = $this->stockAssetRepository->findAll();
		$totalPortfolioValue = $this->assetPriceSummaryFacade->getCurrentValue(CurrencyEnum::CZK)->getPrice();

		$data = [];
		foreach ($assets as $asset) {
			if (!$asset->hasOpenPositions()) {
				continue;
			}

			$dto = $this->stockPositionFacade->getStockAssetDetailDTO(
				$asset->getId(),
				StockAssetListDetailControlEnum::OPEN_POSITIONS,
			);
			$valuations = $this->stockValuationDataRepository->findLatestForStockAsset($asset);

			$firstPurchaseDate = null;
			foreach ($dto->getPositions() as $positionDto) {
				$position = $positionDto->getStockPosition();
				if ($firstPurchaseDate === null || $position->getOrderDate() < $firstPurchaseDate) {
					$firstPurchaseDate = $position->getOrderDate();
				}
			}

			$portfolioPercentage = $totalPortfolioValue > 0
				? $dto->getCurrentPriceInCzk()->getPrice() / $totalPortfolioValue * 100
				: 0;

			$data[] = [
				'stockAssetId' => $asset->getId()->toString(),
				'stockAssetName' => $asset->getName(),
				'stockAssetTicker' => $asset->getTicker(),
				'currency' => $asset->getCurrency()->value,
				'sector' => $asset->getIndustry()?->getName(),
				'currentPrice' => $asset->getAssetCurrentPrice()->getPrice(),
				'averagePurchasePrice' => $dto->getPiecesCount() > 0
					? $dto->getTotalInvestedAmount()->getPrice() / $dto->getPiecesCount()
					: 0,
				'portfolioPercentage' => round($portfolioPercentage, 2),
				'profitLossPercent' => round($dto->getCurrentPriceDiff()->getPercentageDifference(), 2),
				'firstPurchaseDate' => $firstPurchaseDate?->format('Y-m-d'),
				'dividendYield' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
				),
				'trailingDividendYield' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
				),
				'payoutRatio' => $this->getValuationValue($valuations, StockValuationTypeEnum::PAYOUT_RATIO),
				'trailingPE' => $this->getValuationValue($valuations, StockValuationTypeEnum::TRAILING_PE),
				'forwardPE' => $this->getValuationValue($valuations, StockValuationTypeEnum::FORWARD_PE),
				'priceToBook' => $this->getValuationValue($valuations, StockValuationTypeEnum::PRICE_BOOK),
				'pegRatio' => $this->getValuationValue($valuations, StockValuationTypeEnum::PEG_RATIO),
				'profitMargin' => $this->getValuationValue($valuations, StockValuationTypeEnum::PROFIT_MARGIN),
				'returnOnEquity' => $this->getValuationValue($valuations, StockValuationTypeEnum::RETURN_ON_EQUITY),
				'debtToEquity' => $this->getValuationValue($valuations, StockValuationTypeEnum::TOTAL_DEBT_EQUITY),
				'52WeekHigh' => $this->getValuationValue($valuations, StockValuationTypeEnum::WEEK_52_HIGH),
				'52WeekLow' => $this->getValuationValue($valuations, StockValuationTypeEnum::WEEK_52_LOW),
				'analystTargetPrice' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE,
				),
				'quarterlyEarningsGrowth' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
				),
				'quarterlyRevenueGrowth' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::QUARTERLY_REVENUE_GROWTH,
				),
				'performance1Day' => $this->getPerformance1Day($asset),
				'performance7Days' => $this->getPerformance7Days($asset),
			];
		}

		return $data;
	}

	/**
	 * @return array<mixed>
	 */
	private function getWatchlistData(): array
	{
		$assets = $this->stockAssetRepository->findAll();
		$data = [];
		foreach ($assets as $asset) {
			if (!$asset->isWatchlist()) {
				continue;
			}

			$valuations = $this->stockValuationDataRepository->findLatestForStockAsset($asset);

			$data[] = [
				'stockAssetId' => $asset->getId()->toString(),
				'stockAssetName' => $asset->getName(),
				'stockAssetTicker' => $asset->getTicker(),
				'currency' => $asset->getCurrency()->value,
				'sector' => $asset->getIndustry()?->getName(),
				'currentPrice' => $asset->getAssetCurrentPrice()->getPrice(),
				'trailingPE' => $this->getValuationValue($valuations, StockValuationTypeEnum::TRAILING_PE),
				'forwardPE' => $this->getValuationValue($valuations, StockValuationTypeEnum::FORWARD_PE),
				'priceToBook' => $this->getValuationValue($valuations, StockValuationTypeEnum::PRICE_BOOK),
				'pegRatio' => $this->getValuationValue($valuations, StockValuationTypeEnum::PEG_RATIO),
				'dividendYield' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
				),
				'trailingDividendYield' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
				),
				'payoutRatio' => $this->getValuationValue($valuations, StockValuationTypeEnum::PAYOUT_RATIO),
				'marketCap' => $this->getValuationValue($valuations, StockValuationTypeEnum::MARKET_CAP),
				'52WeekHigh' => $this->getValuationValue($valuations, StockValuationTypeEnum::WEEK_52_HIGH),
				'52WeekLow' => $this->getValuationValue($valuations, StockValuationTypeEnum::WEEK_52_LOW),
				'profitMargin' => $this->getValuationValue($valuations, StockValuationTypeEnum::PROFIT_MARGIN),
				'returnOnEquity' => $this->getValuationValue($valuations, StockValuationTypeEnum::RETURN_ON_EQUITY),
				'debtToEquity' => $this->getValuationValue($valuations, StockValuationTypeEnum::TOTAL_DEBT_EQUITY),
				'revenueGrowth' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::QUARTERLY_REVENUE_GROWTH,
				),
				'analystTargetPrice' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::ANALYST_PRICE_TARGET_AVERAGE,
				),
				'quarterlyEarningsGrowth' => $this->getValuationValue(
					$valuations,
					StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
				),
				'performance1Day' => $this->getPerformance1Day($asset),
				'performance7Days' => $this->getPerformance7Days($asset),
			];
		}

		return $data;
	}

	/**
	 * @param array<string, StockValuationData> $valuations
	 */
	private function getValuationValue(array $valuations, StockValuationTypeEnum $type): float|null
	{
		return isset($valuations[$type->value]) ? $valuations[$type->value]->getFloatValue() : null;
	}

	private function getPerformance1Day(StockAsset $asset): float|null
	{
		return $this->getPerformanceDays($asset, 1);
	}

	private function getPerformance7Days(StockAsset $asset): float|null
	{
		return $this->getPerformanceDays($asset, 7);
	}

	private function getPerformanceDays(StockAsset $asset, int $days): float|null
	{
		$now = $this->datetimeFactory->createNow();
		$sinceDate = $now->deductDaysFromDatetime($days);

		$priceRecords = $this->stockAssetPriceRecordRepository->findByStockAssetSinceDate(
			$asset,
			$sinceDate,
		);

		if (count($priceRecords) < 2) {
			return null;
		}

		$oldestPrice = $priceRecords[0]->getPrice();
		$newestPrice = $priceRecords[count($priceRecords) - 1]->getPrice();

		if ($oldestPrice <= 0) {
			return null;
		}

		return round(($newestPrice - $oldestPrice) / $oldestPrice * 100, 2);
	}

}
