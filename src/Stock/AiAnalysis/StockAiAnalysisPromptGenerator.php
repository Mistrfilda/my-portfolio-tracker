<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Position\StockPositionFacade;
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
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function generate(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
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
			$parts[] = $this->loadPrompt('portfolio/portfolio');
		}

		$watchlistData = [];
		if ($includesWatchlist) {
			$watchlistData = $this->getWatchlistData();
			$parts[] = $this->loadPrompt('portfolio/watchlist');
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
				$stockTicker !== null && $stockName !== null,
			),
			pretty: true,
		);

		$parts[] = 'Data k analýze:';

		$data = [];
		if ($includesPortfolio) {
			$data['portfolio'] = $portfolioData;
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
	 * @return array<string, mixed>
	 */
	private function buildJsonSchema(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		bool $includesStockAnalysis,
	): array
	{
		$schema = [];

		if ($includesMarketOverview) {
			$schema['marketOverview'] = [
				'summary' => 'string',
				'sentiment' => 'bullish | bearish | neutral',
			];
		}

		if ($includesStockAnalysis) {
			$schema['stockAnalysis'] = [
				'businessSummary' => 'string',
				'moatAnalysis' => 'string',
				'financialHealth' => 'string',
				'growthCatalysts' => 'string',
				'risks' => 'string',
				'valuationAssessment' => 'string',
				'conclusion' => 'string',
				'recommendation' => 'consider_buying | hold | consider_selling',
				'fairPrice' => 'float',
				'fairPriceCurrency' => 'USD | EUR | CZK | ...',
			];
		}

		if ($includesPortfolio) {
			$schema['portfolioAnalysis'] = [
				[
					'stockAssetId' => 'uuid',
					'stockAssetName' => 'string',
					'stockAssetTicker' => 'string',
					'positiveNews' => 'string',
					'negativeNews' => 'string',
					'interestingNews' => 'string',
					'aiOpinion' => 'string',
					'actionSuggestion' => 'hold | consider_selling | add_more | watch_closely',
					'fairPrice' => 'float',
					'fairPriceCurrency' => 'USD | EUR | CZK | ...',
				],
			];
		}

		if ($includesWatchlist) {
			$schema['watchlistAnalysis'] = [
				[
					'stockAssetId' => 'uuid',
					'stockAssetName' => 'string',
					'stockAssetTicker' => 'string',
					'news' => 'string',
					'buyRecommendation' => 'consider_buying | wait | not_interesting',
					'reasoning' => 'string',
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
				'dividendYield' => isset($valuations[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value])
					? $valuations[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value]->getFloatValue()
					: null,
				'trailingPE' => isset($valuations[StockValuationTypeEnum::TRAILING_PE->value])
					? $valuations[StockValuationTypeEnum::TRAILING_PE->value]->getFloatValue()
					: null,
				'forwardPE' => isset($valuations[StockValuationTypeEnum::FORWARD_PE->value])
					? $valuations[StockValuationTypeEnum::FORWARD_PE->value]->getFloatValue()
					: null,
				'priceToBook' => isset($valuations[StockValuationTypeEnum::PRICE_BOOK->value])
					? $valuations[StockValuationTypeEnum::PRICE_BOOK->value]->getFloatValue()
					: null,
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
				'trailingPE' => isset($valuations[StockValuationTypeEnum::TRAILING_PE->value])
					? $valuations[StockValuationTypeEnum::TRAILING_PE->value]->getFloatValue()
					: null,
				'forwardPE' => isset($valuations[StockValuationTypeEnum::FORWARD_PE->value])
					? $valuations[StockValuationTypeEnum::FORWARD_PE->value]->getFloatValue()
					: null,
				'priceToBook' => isset($valuations[StockValuationTypeEnum::PRICE_BOOK->value])
					? $valuations[StockValuationTypeEnum::PRICE_BOOK->value]->getFloatValue()
					: null,
				'pegRatio' => isset($valuations[StockValuationTypeEnum::PEG_RATIO->value])
					? $valuations[StockValuationTypeEnum::PEG_RATIO->value]->getFloatValue()
					: null,
				'dividendYield' => isset($valuations[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value])
					? $valuations[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value]->getFloatValue()
					: null,
				'marketCap' => isset($valuations[StockValuationTypeEnum::MARKET_CAP->value])
					? $valuations[StockValuationTypeEnum::MARKET_CAP->value]->getFloatValue()
					: null,
				'52WeekHigh' => isset($valuations[StockValuationTypeEnum::WEEK_52_HIGH->value])
					? $valuations[StockValuationTypeEnum::WEEK_52_HIGH->value]->getFloatValue()
					: null,
				'52WeekLow' => isset($valuations[StockValuationTypeEnum::WEEK_52_LOW->value])
					? $valuations[StockValuationTypeEnum::WEEK_52_LOW->value]->getFloatValue()
					: null,
			];
		}

		return $data;
	}

}
