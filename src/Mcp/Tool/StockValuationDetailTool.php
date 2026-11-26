<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Mcp\Support\StockAssetResolver;
use App\Stock\AiAnalysis\StockAiAnalysisCompanyDataProvider;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Valuation\Comparison\Industry\StockIndustryComparisonFacade;
use App\Stock\Valuation\Comparison\Industry\StockIndustryComparisonMetric;
use App\Stock\Valuation\Data\StockValuationDataChange;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyProvider;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\StockValuationFacade;
use App\Stock\Valuation\StockValuationPriceProvider;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class StockValuationDetailTool
{

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockAssetResolver $stockAssetResolver,
		private readonly StockAiAnalysisCompanyDataProvider $companyDataProvider,
		private readonly StockValuationFacade $valuationFacade,
		private readonly StockValuationModelConsensusProvider $consensusProvider,
		private readonly StockValuationPriceProvider $valuationPriceProvider,
		private readonly StockValuationMarginOfSafetyProvider $marginOfSafetyProvider,
		private readonly StockIndustryComparisonFacade $industryComparisonFacade,
		private readonly StockPositionFacade $stockPositionFacade,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(
		string|null $ticker = null,
		string|null $stockAssetId = null,
		bool $includeModelScenarios = true,
	): array
	{
		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();
		$asset = $this->stockAssetResolver->resolve($ticker, $stockAssetId);

		$companyData = $this->companyDataProvider->getData($asset);
		if (!$includeModelScenarios && is_array($companyData['internalValuation'] ?? null)) {
			$internalValuation = $companyData['internalValuation'];
			if (is_array($internalValuation['models'] ?? null)) {
				$trimmedModels = [];
				foreach ($internalValuation['models'] as $model) {
					if (is_array($model)) {
						unset($model['scenarios']);
					}

					$trimmedModels[] = $model;
				}

				$internalValuation['models'] = $trimmedModels;
				$companyData['internalValuation'] = $internalValuation;
			}
		}

		$valuation = $this->valuationFacade->getStockValuation($asset->getId());
		$modelResponses = $this->valuationFacade->getModelsForValuation($valuation);
		$consensus = $this->consensusProvider->getForStockAsset($asset, $modelResponses);
		$analyticsPrice = $this->valuationPriceProvider->getAnalyticsPrice($asset);
		$aiPrice = $this->valuationPriceProvider->getAiAnalysisPrice($asset, $owner);
		$marginOfSafety = $this->marginOfSafetyProvider->getForStockAsset(
			$asset,
			$consensus,
			$analyticsPrice,
			$aiPrice,
		);

		$industryComparison = $this->industryComparisonFacade->getComparison($valuation);
		$positionDetail = $this->stockPositionFacade->getStockAssetDetailDTO(
			$asset->getId(),
			StockAssetListDetailControlEnum::OPEN_POSITIONS,
			$owner,
		);

		$warnings = [];
		if ($valuation->getImportedAt() === null) {
			$warnings[] = 'No fundamental valuation data has been imported for this stock yet.';
		}

		if ($asset->getIndustry() === null) {
			$warnings[] = 'No industry/sector is assigned to this stock; industry comparison is unavailable.';
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'importedAt' => $valuation->getImportedAt()?->format(DATE_ATOM),
			'previousImportedAt' => $valuation->getPreviousImportedAt()?->format(DATE_ATOM),
			'companyData' => $companyData,
			'marginOfSafety' => [
				'fairPriceEstimate' => $marginOfSafety->getFairPriceEstimate() !== null
					? round($marginOfSafety->getFairPriceEstimate()->getPrice(), 4)
					: null,
				'currency' => $marginOfSafety->getFairPriceEstimate()?->getCurrency()->value ?? $asset->getCurrency()->value,
				'marginPercentage' => $marginOfSafety->getMarginPercentage() !== null
					? round($marginOfSafety->getMarginPercentage(), 2)
					: null,
				'sourceSpreadPercentage' => $marginOfSafety->getSourceSpreadPercentage() !== null
					? round($marginOfSafety->getSourceSpreadPercentage(), 2)
					: null,
				'externalEstimate' => $marginOfSafety->getExternalEstimate() !== null
					? round($marginOfSafety->getExternalEstimate()->getPrice(), 4)
					: null,
				'analyticsTargetPrice' => $analyticsPrice !== null ? round($analyticsPrice->getPrice(), 4) : null,
				'aiTargetPrice' => $aiPrice !== null ? round($aiPrice->getPrice(), 4) : null,
				'sourceGroupsCount' => $marginOfSafety->getSourceGroupsCount(),
				'inputEstimatesCount' => $marginOfSafety->getInputEstimatesCount(),
				'status' => $marginOfSafety->getStatus()->value,
				'confidence' => $marginOfSafety->getConfidence()->value,
				'reasons' => $marginOfSafety->getReasons(),
			],
			'industryComparison' => [
				'industryName' => $asset->getIndustry()?->getName(),
				'hasIndustryData' => $industryComparison->hasIndustryData(),
				'overallScore' => $industryComparison->getOverallScore(),
				'metrics' => array_map(
					static fn (StockIndustryComparisonMetric $metric): array => [
						'metric' => $metric->getMetric()->value,
						'label' => $metric->getMetric()->format(),
						'stockValue' => round($metric->getStockValue(), 4),
						'industryAverage' => $metric->getIndustryAverage() !== null
							? round($metric->getIndustryAverage(), 4)
							: null,
						'deviationPercent' => $metric->getDeviationPercentage() !== null
							? round($metric->getDeviationPercentage(), 2)
							: null,
						'state' => $metric->getState()->value,
						'isPositive' => $metric->isPositive(),
					],
					$industryComparison->getMetrics(),
				),
			],
			'fundamentalChanges' => array_map(
				static fn (StockValuationDataChange $change): array => [
					'metric' => $change->type->value,
					'label' => $change->type->format(),
					'changeType' => $change->getLabel(),
					'previousValue' => $change->previous?->getFloatValue(),
					'previousCurrency' => $change->type->isCurrencyValue()
						? $change->previous?->getCurrency()->value
						: null,
					'currentValue' => $change->current?->getFloatValue(),
					'currentCurrency' => $change->type->isCurrencyValue()
						? $change->current?->getCurrency()->value
						: null,
				],
				$valuation->getInputChanges(),
			),
			'positionSummary' => $positionDetail->getPositions() === []
				? null
				: [
					'pieces' => $positionDetail->getPiecesCount(),
					'averagePurchasePrice' => round($positionDetail->getAveragePurchasePrice(), 4),
					'investedAmount' => round($positionDetail->getTotalInvestedAmount()->getPrice(), 2),
					'currentValue' => round($positionDetail->getCurrentAmount()->getPrice(), 2),
					'currency' => $asset->getCurrency()->value,
					'currentValueCzk' => round($positionDetail->getCurrentPriceInCzk()->getPrice(), 2),
					'profit' => round($positionDetail->getCurrentPriceDiff()->getPriceDifference(), 2),
					'profitPercent' => round($positionDetail->getCurrentPriceDiff()->getPercentageDifference(), 2),
				],
			'warnings' => $warnings,
		];
	}

}
