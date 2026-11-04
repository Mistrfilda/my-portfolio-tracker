<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Stock\Asset\StockAsset;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\Model\StockValuationModelPurpose;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationTypeEnum as Type;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use const DATE_ATOM;

class StockAiAnalysisCompanyDataProvider
{

	private const array FUNDAMENTALS = [
		'trailingPE' => Type::TRAILING_PE,
		'forwardPE' => Type::FORWARD_PE,
		'priceToBook' => Type::PRICE_BOOK,
		'priceToSales' => Type::PRICE_SALES,
		'pegRatio' => Type::PEG_RATIO,
		'evEbitda' => Type::EV_EBITDA,
		'marketCap' => Type::MARKET_CAP,
		'enterpriseValue' => Type::ENTERPRISE_VALUE,
		'dividendYield' => Type::FORWARD_ANNUAL_DIVIDEND_YIELD,
		'trailingDividendYield' => Type::TRAILING_ANNUAL_DIVIDEND_YIELD,
		'forwardAnnualDividendRate' => Type::FORWARD_ANNUAL_DIVIDEND_RATE,
		'trailingAnnualDividendRate' => Type::TRAILING_ANNUAL_DIVIDEND_RATE,
		'payoutRatio' => Type::PAYOUT_RATIO,
		'profitMargin' => Type::PROFIT_MARGIN,
		'operatingMargin' => Type::OPERATING_MARGIN,
		'returnOnEquity' => Type::RETURN_ON_EQUITY,
		'returnOnAssets' => Type::RETURN_ON_ASSETS,
		'debtToEquity' => Type::TOTAL_DEBT_EQUITY,
		'totalDebt' => Type::TOTAL_DEBT,
		'totalCash' => Type::TOTAL_CASH,
		'currentRatio' => Type::CURRENT_RATIO,
		'bookValuePerShare' => Type::BOOK_VALUE_PER_SHARE,
		'dilutedEps' => Type::DILUTED_EPS,
		'revenueTtm' => Type::REVENUE_TTM,
		'revenuePerShare' => Type::REVENUE_PER_SHARE,
		'ebitda' => Type::EBITDA,
		'netIncome' => Type::NET_INCOME,
		'operatingCashFlow' => Type::OPERATING_CASH_FLOW,
		'leveredFreeCashFlow' => Type::LEVERED_FREE_CASH_FLOW,
		'sharesOutstanding' => Type::SHARES_OUTSTANDING,
		'quarterlyEarningsGrowth' => Type::QUARTERLY_EARNINGS_GROWTH,
		'quarterlyRevenueGrowth' => Type::QUARTERLY_REVENUE_GROWTH,
		'mostRecentQuarter' => Type::MOST_RECENT_QUARTER,
		'52WeekHigh' => Type::WEEK_52_HIGH,
		'52WeekLow' => Type::WEEK_52_LOW,
		'analystTargetPrice' => Type::ANALYST_PRICE_TARGET_AVERAGE,
	];

	public function __construct(
		private readonly StockValuationFacade $valuationFacade,
		private readonly StockValuationModelConsensusProvider $consensusProvider,
		private readonly StockAssetPriceRecordRepository $priceRecordRepository,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	/** @return array<string, mixed> */
	public function getData(StockAsset $asset): array
	{
		$valuation = $this->valuationFacade->getStockValuation($asset->getId());
		$now = $this->datetimeFactory->createNow();
		$data = [
			'stockAssetId' => $asset->getId()->toString(),
			'stockAssetName' => $asset->getName(),
			'stockAssetTicker' => $asset->getTicker(),
			'currency' => $asset->getCurrency()->value,
			'sector' => $asset->getIndustry()?->getName(),
			'currentPrice' => $this->finiteValue($asset->getAssetCurrentPrice()->getPrice()),
			'priceDownloadedAt' => $asset->getPriceDownloadedAt()?->format(DATE_ATOM),
			'fundamentalDataSource' => 'Yahoo Finance imports',
		];
		$metadata = [];
		foreach (self::FUNDAMENTALS as $key => $type) {
			$metric = $valuation->getValuationDataByType($type);
			$data[$key] = $type->getTypeValueType() === StockValuationTypeValueTypeEnum::TEXT
				? $metric?->getStringValue()
				: $this->finiteValue($metric?->getFloatValue());
			if ($metric !== null) {
				$metadata[$key] = [
					'metric' => $type->value,
					'importedAt' => $metric->getParsedAt()->format(DATE_ATOM),
					'valueType' => $type->getTypeValueType()->value,
					'currency' => $type->isCurrencyValue() ? $metric->getCurrency()->value : null,
				];
			}
		}

		$data['fundamentalMetadata'] = $metadata;
		$data['performance1Day'] = $this->getPerformance($asset, $now, 1);
		$data['performance7Days'] = $this->getPerformance($asset, $now, 7);
		$data['internalValuation'] = $this->getInternalValuation($valuation);

		return $data;
	}

	/** @return array<string, mixed> */
	private function getInternalValuation(StockValuation $valuation): array
	{
		$asset = $valuation->getStockAsset();
		$responses = $this->valuationFacade->getModelsForValuation($valuation);
		$consensus = $this->consensusProvider->getForStockAsset($asset, $responses);
		$models = [];
		foreach ($responses as $response) {
			$models[] = $this->getModelData($response);
		}

		$families = [];
		foreach ($consensus->getFamilyEstimates() as $family) {
			$families[] = [
				'family' => $family->getFamily()->value,
				'fairValue' => $family->getPrice()?->getPrice(),
				'validModelsCount' => $family->getValidModelsCount(),
				'totalModelsCount' => $family->getTotalModelsCount(),
			];
		}

		return [
			'industry' => [
				'name' => $asset->getIndustry()?->getName(),
				'mappingName' => $asset->getIndustry()?->getMappingName(),
			],
			'models' => $models,
			'consensus' => [
				'fairValue' => $consensus->getPrice()?->getPrice(),
				'currency' => $asset->getCurrency()->value,
				'upsidePercent' => $this->finiteValue($consensus->getPercentage()),
				'lowerQuartile' => $consensus->getLowerQuartilePrice()?->getPrice(),
				'upperQuartile' => $consensus->getUpperQuartilePrice()?->getPrice(),
				'normalizedIqrPercent' => $this->finiteValue($consensus->getNormalizedIqrPercentage()),
				'confidence' => $consensus->getConfidence()->value,
				'validModelsCount' => $consensus->getValidModelsCount(),
				'totalModelsCount' => $consensus->getTotalModelsCount(),
				'validFamiliesCount' => $consensus->getValidFamiliesCount(),
				'totalFamiliesCount' => $consensus->getTotalFamiliesCount(),
				'outlierModelLabels' => $consensus->getOutlierModelLabels(),
				'families' => $families,
			],
		];
	}

	/** @return array<string, mixed> */
	private function getModelData(StockValuationModelResponse $response): array
	{
		$assumptions = [];
		foreach ($response->getModelUsedValues() as $value) {
			$rawValue = $value->getValue();
			$assumptions[] = [
				'label' => $value->getLabel(),
				'value' => is_float($rawValue) ? $this->finiteValue($rawValue) : $rawValue,
			];
		}

		$scenarios = [];
		foreach ($response->getScenarios() as $scenario) {
			$scenarios[] = [
				'label' => $scenario->label,
				'fairValue' => $scenario->price->getPrice(),
				'currency' => $scenario->price->getCurrency()->value,
				'requiredReturnPercent' => $scenario->requiredReturn * 100,
				'growthPercent' => $scenario->growth * 100,
			];
		}

		$isDiagnostic = $response->getPurpose() === StockValuationModelPurpose::DIAGNOSTIC;
		return [
			'model' => $response->getStockValuationModel()->getName(),
			'label' => $response->getLabel(),
			'purpose' => $response->getPurpose()->value,
			'availability' => $response->getAvailability()->value,
			'availabilityReason' => $response->getAvailabilityReason(),
			'fairValue' => $response->getAssetPrice()?->getPrice(),
			'currency' => $response->getAssetPrice()?->getCurrency()->value,
			'upsidePercent' => $this->finiteValue($response->getCalculatedPercentage()),
			'diagnosticValue' => $isDiagnostic ? $this->finiteValue($response->getCalculatedValue()) : null,
			'diagnosticUnit' => $isDiagnostic ? $response->getValueUnit() : null,
			'inputMetrics' => array_map(
				static fn (Type $type): string => $type->value,
				$response->getUsedStockValuationDataTypes(),
			),
			'assumptions' => $assumptions,
			'description' => $response->getDescription(),
			'scenarios' => $scenarios,
		];
	}

	private function finiteValue(float|null $value): float|null
	{
		return $value !== null && is_finite($value) ? $value : null;
	}

	private function getPerformance(StockAsset $asset, ImmutableDateTime $now, int $days): float|null
	{
		$records = $this->priceRecordRepository->findByStockAssetSinceDate($asset, $now->deductDaysFromDatetime($days));
		if (count($records) < 2) {
			return null;
		}

		$oldestPrice = $records[0]->getPrice();
		$newestPrice = $records[count($records) - 1]->getPrice();
		if ($oldestPrice <= 0) {
			return null;
		}

		return $this->finiteValue(round(($newestPrice - $oldestPrice) / $oldestPrice * 100, 2));
	}

}
