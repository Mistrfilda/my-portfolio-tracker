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

class StockAiAnalysisPromptGenerator
{

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
	): string
	{
		$now = $this->datetimeFactory->createNow();
		$prompt = 'Jsi zkušený finanční analytik se specializací na akciové trhy. Dnešní datum je ' . $now->format(
			'd. m. Y',
		) . ". Odpovídej prosím v českém jazyce.\n\n";

		if ($includesMarketOverview) {
			$prompt .= 'Analyzuj aktuální situaci na trhu (sentiment, klíčové události, makro trendy) ' .
				"a uveď ji v sekci marketOverview. Shrnutí by mělo mít 3–5 vět.\n\n";
		}

		$portfolioData = [];
		if ($includesPortfolio) {
			$portfolioData = $this->getPortfolioData();
			$prompt .= "Analyzuj mé aktuální akciové pozice. Pro každou akcii v seznamu portfolioAnalysis uveď:\n";
			$prompt .= "a) Pozitivní zprávy (3–4 věty)\n";
			$prompt .= "b) Negativní zprávy (3–4 věty)\n";
			$prompt .= "c) Zajímavé novinky (3–4 věty)\n";
			$prompt .= "d) Tvůj obecný názor\n";
			$prompt .= "e) Doporučení (hold, consider_selling, add_more, watch_closely)\n\n";
		}

		$watchlistData = [];
		if ($includesWatchlist) {
			$watchlistData = $this->getWatchlistData();
			$prompt .= "Analyzuj akcie na mém watchlistu. Pro každou akcii v sekci watchlistAnalysis uveď:\n";
			$prompt .= "a) Aktuální zprávy a novinky\n";
			$prompt .= "b) Zda dává smysl ji nyní nakoupit a proč / proč ne\n";
			$prompt .= "c) Doporučení (consider_buying, wait, not_interesting)\n\n";
		}

		$prompt .= "Výstup musí být validní JSON v následujícím formátu:\n";
		$prompt .= "{\n";
		if ($includesMarketOverview) {
			$prompt .= "  \"marketOverview\": {\n";
			$prompt .= "    \"summary\": \"string\",\n";
			$prompt .= "    \"sentiment\": \"bullish | bearish | neutral\"\n";
			$prompt .= "  },\n";
		}

		if ($includesPortfolio) {
			$prompt .= "  \"portfolioAnalysis\": [\n";
			$prompt .= "    {\n";
			$prompt .= "      \"stockAssetId\": \"uuid\",\n";
			$prompt .= "      \"stockAssetName\": \"string\",\n";
			$prompt .= "      \"stockAssetTicker\": \"string\",\n";
			$prompt .= "      \"positiveNews\": \"string\",\n";
			$prompt .= "      \"negativeNews\": \"string\",\n";
			$prompt .= "      \"interestingNews\": \"string\",\n";
			$prompt .= "      \"aiOpinion\": \"string\",\n";
			$prompt .= "      \"actionSuggestion\": \"hold | consider_selling | add_more | watch_closely\"\n";
			$prompt .= "    }\n";
			$prompt .= "  ],\n";
		}

		if ($includesWatchlist) {
			$prompt .= "  \"watchlistAnalysis\": [\n";
			$prompt .= "    {\n";
			$prompt .= "      \"stockAssetId\": \"uuid\",\n";
			$prompt .= "      \"stockAssetName\": \"string\",\n";
			$prompt .= "      \"stockAssetTicker\": \"string\",\n";
			$prompt .= "      \"news\": \"string\",\n";
			$prompt .= "      \"buyRecommendation\": \"consider_buying | wait | not_interesting\",\n";
			$prompt .= "      \"reasoning\": \"string\"\n";
			$prompt .= "    }\n";
			$prompt .= "  ]\n";
		}

		$prompt .= "}\n\n";

		$prompt .= "Data k analýze:\n";
		$data = [];
		if ($includesPortfolio) {
			$data['portfolio'] = $portfolioData;
		}

		if ($includesWatchlist) {
			$data['watchlist'] = $watchlistData;
		}

		return $prompt . Json::encode($data, pretty: true);
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
