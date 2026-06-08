<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

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
		$parts = [];

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

	public function generateSystemInstruction(): string
	{
		$now = $this->datetimeFactory->createNow();

		return sprintf($this->loadPrompt('common/system'), $now->format('d. m. Y'));
	}

	/**
	 * @return array<string, mixed>
	 */
	public function generateResponseSchema(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType = null,
		string|null $stockTicker = null,
		string|null $stockName = null,
	): array
	{
		return $this->convertJsonSchemaToGeminiResponseSchema($this->buildJsonSchema(
			$includesPortfolio,
			$includesWatchlist,
			$includesMarketOverview,
			$portfolioPromptType,
			$stockTicker !== null && $stockName !== null,
		));
	}

	/**
	 * @return array<mixed>
	 */
	public function getAutomaticPortfolioData(): array
	{
		return $this->getPortfolioData();
	}

	public function generateManualOpenPositionsPrompt(): string
	{
		$positions = [];
		foreach ($this->getPortfolioData() as $portfolioItem) {
			assert(is_array($portfolioItem));

			$positions[] = [
				'stockAssetName' => $portfolioItem['stockAssetName'],
				'stockAssetTicker' => $portfolioItem['stockAssetTicker'],
				'currency' => $portfolioItem['currency'],
				'portfolioPercentage' => $portfolioItem['portfolioPercentage'],
				'profitLossPercent' => $portfolioItem['profitLossPercent'],
				'lastPurchaseDate' => $portfolioItem['lastPurchaseDate'],
				'averagePurchasePrice' => $portfolioItem['averagePurchasePrice'],
			];
		}

		return implode("\n\n", [
			'Seznam aktuálně otevřených akciových pozic v mém portfoliu:',
			Json::encode(['openPositions' => $positions], pretty: true),
		]);
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
		return $this->generateAutomaticStockPrompt(
			'portfolioAnalysis',
			$this->buildPortfolioAnalysisJsonSchema($portfolioPromptType),
			$portfolioItem,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function generateAutomaticPortfolioStockResponseSchema(
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): array
	{
		return $this->convertJsonSchemaToGeminiResponseSchema([
			'portfolioAnalysis' => $this->buildPortfolioAnalysisJsonSchema($portfolioPromptType),
		]);
	}

	/**
	 * @param array<string, mixed> $watchlistItem
	 */
	public function generateAutomaticWatchlistStockPrompt(
		array $watchlistItem,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): string
	{
		return $this->generateAutomaticStockPrompt(
			'watchlistAnalysis',
			$this->buildWatchlistAnalysisJsonSchema($portfolioPromptType),
			$watchlistItem,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function generateAutomaticWatchlistStockResponseSchema(
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): array
	{
		return $this->convertJsonSchemaToGeminiResponseSchema([
			'watchlistAnalysis' => $this->buildWatchlistAnalysisJsonSchema($portfolioPromptType),
		]);
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
		$schema = $this->buildAutomaticReduceJsonSchema(
			$includesPortfolio,
			$includesMarketOverview,
			$portfolioPromptType,
		);

		return implode("\n\n", [
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
	 * @return array<string, mixed>
	 */
	public function generateAutomaticReduceResponseSchema(
		bool $includesPortfolio,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): array
	{
		return $this->convertJsonSchemaToGeminiResponseSchema($this->buildAutomaticReduceJsonSchema(
			$includesPortfolio,
			$includesMarketOverview,
			$portfolioPromptType,
		));
	}

	/**
	 * @param array<mixed> $schema
	 * @param array<string, mixed> $stockData
	 */
	private function generateAutomaticStockPrompt(string $rootKey, array $schema, array $stockData): string
	{
		return implode("\n\n", [
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
			$schema['marketOverview'] = $this->buildMarketOverviewJsonSchema();
		}

		if ($includesPortfolio && $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
			$schema['dailyBrief'] = $this->buildDailyBriefJsonSchema();
		}

		if ($includesPortfolio && $portfolioPromptType !== StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
			$schema['portfolioEvaluation'] = $this->buildPortfolioEvaluationJsonSchema();
		}

		if ($includesStockAnalysis) {
			$schema['stockAnalysis'] = $this->buildStockAnalysisJsonSchema();
		}

		if ($includesPortfolio) {
			$schema['portfolioAnalysis'] = $this->buildPortfolioAnalysisJsonSchema($portfolioPromptType);
		}

		if ($includesWatchlist) {
			$schema['watchlistAnalysis'] = $this->buildWatchlistAnalysisJsonSchema($portfolioPromptType);
		}

		return $schema;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildAutomaticReduceJsonSchema(
		bool $includesPortfolio,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): array
	{
		$schema = [];

		if ($includesMarketOverview) {
			$schema['marketOverview'] = $this->buildMarketOverviewJsonSchema();
		}

		if ($includesPortfolio && $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF) {
			$schema['dailyBrief'] = $this->buildDailyBriefJsonSchema();
		} elseif ($includesPortfolio) {
			$schema['portfolioEvaluation'] = $this->buildPortfolioEvaluationJsonSchema();
		}

		return $schema;
	}

	/**
	 * @return array<string, string>
	 */
	private function buildMarketOverviewJsonSchema(): array
	{
		return [
			'summary' => 'string',
			'sentiment' => 'bullish | bearish | neutral',
			'geopoliticalContext' => 'string',
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function buildDailyBriefJsonSchema(): array
	{
		return [
			'summary' => 'string',
			'marketPulse' => 'string',
			'portfolioImpactSummary' => 'string',
			'watchlistSummary' => 'string',
			'importantAlerts' => 'string',
			'nextDaysChecklist' => 'string',
			'actionNeeded' => 'none | monitor | review_positions | review_watchlist',
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function buildPortfolioEvaluationJsonSchema(): array
	{
		return [
			'summary' => 'string',
			'performance7DaysSummary' => 'string',
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function buildStockAnalysisJsonSchema(): array
	{
		return [
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

	/**
	 * @return array<int, array<string, string>>
	 */
	private function buildPortfolioAnalysisJsonSchema(
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): array
	{
		$performanceCommentField = $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
			? 'performance1DayComment'
			: 'performance7DaysComment';

		return [
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

	/**
	 * @return array<int, array<string, string>>
	 */
	private function buildWatchlistAnalysisJsonSchema(
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType,
	): array
	{
		$performanceCommentField = $portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
			? 'performance1DayComment'
			: 'performance7DaysComment';

		return [
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

	/**
	 * @param array<mixed> $schema
	 * @return array<string, mixed>
	 */
	private function convertJsonSchemaToGeminiResponseSchema(array $schema): array
	{
		return $this->convertJsonSchemaValueToGeminiSchema($schema);
	}

	/**
	 * @param array<mixed>|string $schema
	 * @return array<string, mixed>
	 */
	private function convertJsonSchemaValueToGeminiSchema(array|string $schema): array
	{
		if (is_string($schema)) {
			return $this->convertJsonSchemaStringToGeminiSchema($schema);
		}

		if ($this->isJsonListSchema($schema)) {
			$itemSchema = $schema[0] ?? [];
			assert(is_array($itemSchema) || is_string($itemSchema));

			return [
				'type' => 'ARRAY',
				'items' => $this->convertJsonSchemaValueToGeminiSchema($itemSchema),
			];
		}

		$properties = [];
		foreach ($schema as $key => $value) {
			if (!is_string($key)) {
				continue;
			}

			assert(is_array($value) || is_string($value));

			$properties[$key] = $this->convertJsonSchemaValueToGeminiSchema($value);
		}

		return [
			'type' => 'OBJECT',
			'properties' => $properties,
			'required' => array_keys($properties),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function convertJsonSchemaStringToGeminiSchema(string $schema): array
	{
		if ($schema === 'float') {
			return [
				'type' => 'NUMBER',
			];
		}

		$geminiSchema = [
			'type' => 'STRING',
		];

		if (str_contains($schema, ' | ')) {
			$values = array_filter(explode(' | ', $schema), static fn (string $value): bool => $value !== '...');
			if ($values !== []) {
				$geminiSchema['enum'] = array_values($values);
			}
		}

		return $geminiSchema;
	}

	/**
	 * @param array<mixed> $schema
	 */
	private function isJsonListSchema(array $schema): bool
	{
		return array_key_exists(0, $schema) && count($schema) === 1;
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

		$data = [];
		$currentPricesInCzk = [];
		$totalPortfolioValue = 0.0;
		foreach ($assets as $asset) {
			if (!$asset->hasOpenPositions()) {
				continue;
			}

			$dto = $this->stockPositionFacade->getStockAssetDetailDTO(
				$asset->getId(),
				StockAssetListDetailControlEnum::OPEN_POSITIONS,
			);
			$currentPriceInCzk = $dto->getCurrentPriceInCzk()->getPrice();
			$currentPricesInCzk[] = $currentPriceInCzk;
			$totalPortfolioValue += $currentPriceInCzk;
			$valuations = $this->stockValuationDataRepository->findLatestForStockAsset($asset);

			$firstPurchaseDate = null;
			$lastPurchaseDate = null;
			foreach ($dto->getPositions() as $positionDto) {
				$position = $positionDto->getStockPosition();
				if ($firstPurchaseDate === null || $position->getOrderDate() < $firstPurchaseDate) {
					$firstPurchaseDate = $position->getOrderDate();
				}

				if ($lastPurchaseDate === null || $position->getOrderDate() > $lastPurchaseDate) {
					$lastPurchaseDate = $position->getOrderDate();
				}
			}

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
				'portfolioPercentage' => 0.0,
				'profitLossPercent' => round($dto->getCurrentPriceDiff()->getPercentageDifference(), 2),
				'firstPurchaseDate' => $firstPurchaseDate?->format('Y-m-d'),
				'lastPurchaseDate' => $lastPurchaseDate?->format('Y-m-d'),
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

		foreach ($data as $index => $portfolioItem) {
			$portfolioPercentage = $totalPortfolioValue > 0
				? $currentPricesInCzk[$index] / $totalPortfolioValue * 100
				: 0;

			$data[$index]['portfolioPercentage'] = round($portfolioPercentage, 2);
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
