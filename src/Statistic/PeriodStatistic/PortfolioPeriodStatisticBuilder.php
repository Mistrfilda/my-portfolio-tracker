<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

use App\Asset\Asset;
use App\Asset\Position\AssetClosedPosition;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceRecord;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\Closed\CryptoClosedPositionRepository;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Portu\Asset\PortuAsset;
use App\Portu\Asset\PortuAssetRepository;
use App\Portu\Position\PortuPosition;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartPointDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticSummaryDTO;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Statistic\Total\PortfolioStatisticTotalValue;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Doctrine\ORM\NoResultException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPeriodStatisticBuilder
{

	public function __construct(
		private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private StockClosedPositionRepository $stockClosedPositionRepository,
		private CryptoClosedPositionRepository $cryptoClosedPositionRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
		private StockAssetRepository $stockAssetRepository,
		private CryptoAssetRepository $cryptoAssetRepository,
		private PortuAssetRepository $portuAssetRepository,
		private StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private CryptoAssetPriceRecordRepository $cryptoAssetPriceRecordRepository,
		private PortuAssetPriceRecordRepository $portuAssetPriceRecordRepository,
	)
	{
	}

	public function build(PortfolioPeriodStatistic $report): PortfolioPeriodStatisticBuildResult
	{
		$startRecord = $this->portfolioStatisticRecordRepository->findFirstBetweenDates(
			$report->getRequestedStartAt(),
			$report->getRequestedEndAt(),
		);
		$endRecord = $this->portfolioStatisticRecordRepository->findLastBetweenDates(
			$report->getRequestedStartAt(),
			$report->getRequestedEndAt(),
		);

		if ($startRecord === null || $endRecord === null) {
			throw new PortfolioPeriodStatisticUnableToBuildException(
				'At least two portfolio statistic snapshots are required in the selected period.',
			);
		}

		$effectiveStartAt = $startRecord->getCreatedAt();
		$effectiveEndAt = $endRecord->getCreatedAt();
		if ($effectiveStartAt->format('Y-m-d') === $effectiveEndAt->format('Y-m-d')) {
			throw new PortfolioPeriodStatisticUnableToBuildException(
				'Portfolio statistic snapshots must cover at least two different days.',
			);
		}

		$warnings = $this->buildRangeWarnings($report, $effectiveStartAt, $effectiveEndAt);
		$dividendSection = $this->buildDividendSection($effectiveStartAt, $effectiveEndAt);
		$assetSection = $this->buildAssetSection($effectiveStartAt, $effectiveEndAt, $dividendSection);
		$summary = $this->buildSummary(
			$startRecord,
			$endRecord,
			$dividendSection,
			$warnings,
		);
		$chartSection = $this->buildChartSection(
			$effectiveStartAt,
			$effectiveEndAt,
			$dividendSection,
		);

		return new PortfolioPeriodStatisticBuildResult(
			$effectiveStartAt,
			$effectiveEndAt,
			$summary,
			$assetSection,
			$dividendSection,
			$chartSection,
		);
	}

	/**
	 * @param array<string> $warnings
	 */
	private function buildSummary(
		PortfolioStatisticRecord $startRecord,
		PortfolioStatisticRecord $endRecord,
		PortfolioPeriodStatisticDividendSectionDTO $dividendSection,
		array $warnings,
	): PortfolioPeriodStatisticSummaryDTO
	{
		$investedAtStart = $this->getRecordValue($startRecord, PortolioStatisticType::TOTAL_INVESTED_IN_CZK);
		$investedAtEnd = $this->getRecordValue($endRecord, PortolioStatisticType::TOTAL_INVESTED_IN_CZK);
		$valueAtStart = $this->getRecordValue($startRecord, PortolioStatisticType::TOTAL_VALUE_IN_CZK);
		$valueAtEnd = $this->getRecordValue($endRecord, PortolioStatisticType::TOTAL_VALUE_IN_CZK);

		[$closedPositionsProfit, $closedWarnings] = $this->calculateClosedPositionsProfit(
			$startRecord->getCreatedAt(),
			$endRecord->getCreatedAt(),
		);
		$warnings = array_merge($warnings, $closedWarnings, $dividendSection->warnings);

		$value = new PortfolioStatisticTotalValue(
			month: null,
			label: 'Portfolio period statistic',
			investedAtStart: $investedAtStart,
			investedAtEnd: $investedAtEnd,
			valueAtStart: $valueAtStart,
			valueAtEnd: $valueAtEnd,
			closedPositionsProfitInPeriod: $closedPositionsProfit,
			dividendsInPeriod: $dividendSection->netTotalCzk,
			startDate: $startRecord->getCreatedAt(),
			endDate: $endRecord->getCreatedAt(),
			cashFlowData: $this->portfolioStatisticRecordRepository->findDailyInvestedCzkBetweenDates(
				$startRecord->getCreatedAt(),
				$endRecord->getCreatedAt(),
			),
		);

		$valueDifference = $valueAtEnd - $valueAtStart;
		$valueDifferencePercentage = $valueAtStart !== 0.0
			? $valueDifference / $valueAtStart * 100
			: null;

		return new PortfolioPeriodStatisticSummaryDTO(
			investedAtStart: $investedAtStart,
			investedAtEnd: $investedAtEnd,
			investedDifference: $investedAtEnd - $investedAtStart,
			valueAtStart: $valueAtStart,
			valueAtEnd: $valueAtEnd,
			valueDifference: $valueDifference,
			valueDifferencePercentage: $valueDifferencePercentage,
			periodProfit: $value->getPeriodProfit(),
			closedPositionsProfit: $closedPositionsProfit,
			netDividends: $dividendSection->netTotalCzk,
			totalPeriodProfit: $value->getPeriodProfitWithClosedPositionsAndDividends(),
			timeWeightedReturn: $value->getTimeWeightedReturn(),
			annualizedTimeWeightedReturn: $value->getAnnualizedTwr(),
			moneyWeightedReturn: $value->getMoneyWeightedReturn(),
			xirr: $value->getXirr(),
			warnings: array_values(array_unique($warnings)),
			partial: $dividendSection->partial || $closedWarnings !== [],
		);
	}

	private function buildDividendSection(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortfolioPeriodStatisticDividendSectionDTO
	{
		$items = [];
		$warnings = [];
		$grossTotalCzk = 0.0;
		$netTotalCzk = 0.0;
		$partial = false;

		foreach ($this->stockAssetDividendRecordRepository->findBetweenDates($start, $end) as $record) {
			$dividend = $record->getStockAssetDividend();
			$gross = $record->getSummaryPrice(false);
			$net = $record->getSummaryPrice(true);
			$itemWarnings = [];
			$grossCzk = null;
			$netCzk = null;

			try {
				$grossCzk = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$gross,
					CurrencyEnum::CZK,
					$dividend->getExDate(),
				)->getPrice();
				$netCzk = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$net,
					CurrencyEnum::CZK,
					$dividend->getExDate(),
				)->getPrice();
				$grossTotalCzk += $grossCzk;
				$netTotalCzk += $netCzk;
			} catch (MissingCurrencyPairException | NoResultException) {
				$partial = true;
				$itemWarnings[] = 'Chybí historický měnový kurz pro přepočet dividendy.';
				$warnings[] = sprintf(
					'Dividendu %s z %s nebylo možné převést do CZK.',
					$dividend->getExDate()->format('Y-m-d'),
					$dividend->getStockAsset()->getTicker(),
				);
			}

			$items[] = new PortfolioPeriodStatisticDividendDTO(
				recordId: $record->getId()->toString(),
				stockAssetId: $dividend->getStockAsset()->getId()->toString(),
				stockAssetName: $dividend->getStockAsset()->getName(),
				ticker: $dividend->getStockAsset()->getTicker(),
				exDate: $dividend->getExDate()->format('Y-m-d'),
				paymentDate: $dividend->getPaymentDate()?->format('Y-m-d'),
				type: $dividend->getDividendType()->format(),
				pieces: $record->getTotalPiecesHeldAtExDate(),
				currency: $record->getCurrency()->value,
				grossAmount: $gross->getPrice(),
				netAmount: $net->getPrice(),
				grossAmountCzk: $grossCzk,
				netAmountCzk: $netCzk,
				warnings: $itemWarnings,
			);
		}

		usort(
			$items,
			static fn (PortfolioPeriodStatisticDividendDTO $left, PortfolioPeriodStatisticDividendDTO $right): int =>
				$right->exDate <=> $left->exDate,
		);

		return new PortfolioPeriodStatisticDividendSectionDTO(
			count: count($items),
			grossTotalCzk: $grossTotalCzk,
			netTotalCzk: $netTotalCzk,
			taxTotalCzk: $grossTotalCzk - $netTotalCzk,
			dividends: $items,
			warnings: $warnings,
			partial: $partial,
		);
	}

	private function buildAssetSection(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		PortfolioPeriodStatisticDividendSectionDTO $dividendSection,
	): PortfolioPeriodStatisticAssetSectionDTO
	{
		$dividendsByAsset = [];
		foreach ($dividendSection->dividends as $dividend) {
			if ($dividend->netAmountCzk === null) {
				continue;
			}

			$dividendsByAsset[$dividend->stockAssetId] = ($dividendsByAsset[$dividend->stockAssetId] ?? 0.0)
				+ $dividend->netAmountCzk;
		}

		$assets = [];
		foreach ($this->stockAssetRepository->findAll() as $stockAsset) {
			$positions = array_values(array_filter(
				$stockAsset->getPositions(),
				fn (StockPosition $position): bool => $this->positionIntersectsPeriod(
					$position,
					$position->getStockClosedPosition(),
					$start,
					$end,
				),
			));
			if ($positions === []) {
				continue;
			}

			$assets[] = $this->buildExchangeAsset(
				asset: $stockAsset,
				assetType: 'stock',
				ticker: $stockAsset->getTicker(),
				positions: $positions,
				startPriceRecord: $this->stockAssetPriceRecordRepository->findFirstInPeriod(
					$stockAsset,
					$start,
					$end,
				),
				endPriceRecord: $this->stockAssetPriceRecordRepository->findLastInPeriod(
					$stockAsset,
					$start,
					$end,
				),
				closedPositionProvider: static fn (AssetPosition $position): AssetClosedPosition|null =>
					$position instanceof StockPosition ? $position->getStockClosedPosition() : null,
				start: $start,
				end: $end,
				netDividendsCzk: $dividendsByAsset[$stockAsset->getId()->toString()] ?? 0.0,
			);
		}

		foreach ($this->cryptoAssetRepository->findAll() as $cryptoAsset) {
			$positions = array_values(array_filter(
				$cryptoAsset->getPositions(),
				fn (CryptoPosition $position): bool => $this->positionIntersectsPeriod(
					$position,
					$position->getCryptoClosedPosition(),
					$start,
					$end,
				),
			));
			if ($positions === []) {
				continue;
			}

			$assets[] = $this->buildExchangeAsset(
				asset: $cryptoAsset,
				assetType: 'crypto',
				ticker: $cryptoAsset->getTicker(),
				positions: $positions,
				startPriceRecord: $this->cryptoAssetPriceRecordRepository->findFirstInPeriod(
					$cryptoAsset,
					$start,
					$end,
				),
				endPriceRecord: $this->cryptoAssetPriceRecordRepository->findLastInPeriod(
					$cryptoAsset,
					$start,
					$end,
				),
				closedPositionProvider: static fn (AssetPosition $position): AssetClosedPosition|null =>
					$position instanceof CryptoPosition ? $position->getCryptoClosedPosition() : null,
				start: $start,
				end: $end,
				netDividendsCzk: 0.0,
			);
		}

		foreach ($this->portuAssetRepository->findAll() as $portuAsset) {
			$asset = $this->buildPortuAsset($portuAsset, $start, $end);
			if ($asset !== null) {
				$assets[] = $asset;
			}
		}

		usort($assets, static function (
			PortfolioPeriodStatisticAssetDTO $left,
			PortfolioPeriodStatisticAssetDTO $right,
		): int {
			if ($left->marketPerformancePercentage === null && $right->marketPerformancePercentage !== null) {
				return 1;
			}

			if ($left->marketPerformancePercentage !== null && $right->marketPerformancePercentage === null) {
				return -1;
			}

			$performanceComparison = ($right->marketPerformancePercentage ?? 0.0)
				<=> ($left->marketPerformancePercentage ?? 0.0);
			return $performanceComparison !== 0 ? $performanceComparison : $left->name <=> $right->name;
		});

		$warnings = [];
		foreach ($assets as $asset) {
			foreach ($asset->warnings as $warning) {
				$warnings[] = sprintf('%s: %s', $asset->name, $warning);
			}
		}

		return new PortfolioPeriodStatisticAssetSectionDTO($assets, $warnings);
	}

	/**
	 * @param array<AssetPosition> $positions
	 * @param callable(AssetPosition): (AssetClosedPosition|null) $closedPositionProvider
	 */
	private function buildExchangeAsset(
		Asset $asset,
		string $assetType,
		string $ticker,
		array $positions,
		AssetPriceRecord|null $startPriceRecord,
		AssetPriceRecord|null $endPriceRecord,
		callable $closedPositionProvider,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		float $netDividendsCzk,
	): PortfolioPeriodStatisticAssetDTO
	{
		$warnings = [];
		$purchasesCzk = 0.0;
		$salesCzk = 0.0;
		$hasCompleteCashFlowConversion = true;
		$piecesAtStart = 0.0;
		$piecesAtEnd = 0.0;

		foreach ($positions as $position) {
			$closedPosition = $closedPositionProvider($position);
			if ($this->positionActiveAt($position, $closedPosition, $start)) {
				$piecesAtStart += $position->getOrderPiecesCount();
			}

			if ($this->positionActiveAt($position, $closedPosition, $end)) {
				$piecesAtEnd += $position->getOrderPiecesCount();
			}

			if ($this->isBetween($position->getOrderDate(), $start, $end)) {
				$convertedPurchase = $this->convertAssetPriceToCzk(
					$position->getTotalInvestedAmountInBrokerCurrency(),
					$position->getOrderDate(),
					$warnings,
				);
				if ($convertedPurchase === null) {
					$hasCompleteCashFlowConversion = false;
				} else {
					$purchasesCzk += $convertedPurchase;
				}
			}

			if ($closedPosition !== null && $this->isBetween($closedPosition->getDate(), $start, $end)) {
				$convertedSale = $this->convertAssetPriceToCzk(
					$closedPosition->getTotalCloseAmountInBrokerCurrency(),
					$closedPosition->getDate(),
					$warnings,
				);
				if ($convertedSale === null) {
					$hasCompleteCashFlowConversion = false;
				} else {
					$salesCzk += $convertedSale;
				}
			}
		}

		$valueAtStartCzk = $this->calculateBoundaryValue(
			$asset,
			$startPriceRecord,
			$piecesAtStart,
			$warnings,
		);
		$valueAtEndCzk = $this->calculateBoundaryValue(
			$asset,
			$endPriceRecord,
			$piecesAtEnd,
			$warnings,
		);

		$performance = null;
		if (
			$startPriceRecord !== null
			&& $endPriceRecord !== null
			&& $startPriceRecord->getDate()->format('Y-m-d') !== $endPriceRecord->getDate()->format('Y-m-d')
			&& $startPriceRecord->getAssetPrice()->getPrice() !== 0.0
		) {
			$performance = (
				$endPriceRecord->getAssetPrice()->getPrice() / $startPriceRecord->getAssetPrice()->getPrice() - 1
			) * 100;
		} else {
			$warnings[] = 'Pro výpočet tržního výkonu nejsou k dispozici dvě rozdílná cenová data.';
		}

		$capitalResultCzk = $valueAtStartCzk !== null
			&& $valueAtEndCzk !== null
			&& $hasCompleteCashFlowConversion
			? $valueAtEndCzk - $valueAtStartCzk - $purchasesCzk + $salesCzk
			: null;

		return new PortfolioPeriodStatisticAssetDTO(
			assetId: $asset->getId()->toString(),
			assetType: $assetType,
			name: $asset->getName(),
			ticker: $ticker,
			currency: $asset->getCurrency()->value,
			priceStartDate: $startPriceRecord?->getDate()->format('Y-m-d'),
			priceEndDate: $endPriceRecord?->getDate()->format('Y-m-d'),
			priceAtStart: $startPriceRecord?->getAssetPrice()->getPrice(),
			priceAtEnd: $endPriceRecord?->getAssetPrice()->getPrice(),
			marketPerformancePercentage: $performance,
			valueAtStartCzk: $valueAtStartCzk,
			valueAtEndCzk: $valueAtEndCzk,
			purchasesCzk: $purchasesCzk,
			salesCzk: $salesCzk,
			capitalResultCzk: $capitalResultCzk,
			netDividendsCzk: $netDividendsCzk,
			totalContributionCzk: $capitalResultCzk !== null ? $capitalResultCzk + $netDividendsCzk : null,
			warnings: array_values(array_unique($warnings)),
		);
	}

	private function buildPortuAsset(
		PortuAsset $asset,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortfolioPeriodStatisticAssetDTO|null
	{
		$positions = array_values(array_filter(
			$asset->getPositions(),
			static fn (AssetPosition $position): bool => $position->getOrderDate() <= $end,
		));
		if ($positions === []) {
			return null;
		}

		$warnings = [];
		$startValue = 0.0;
		$endValue = 0.0;
		$startInvested = 0.0;
		$endInvested = 0.0;
		$startDate = null;
		$endDate = null;

		foreach ($positions as $position) {
			assert($position instanceof PortuPosition);
			$startRecord = $this->portuAssetPriceRecordRepository->findFirstInPeriod($position, $start, $end);
			$endRecord = $this->portuAssetPriceRecordRepository->findLastInPeriod($position, $start, $end);
			if ($startRecord === null || $endRecord === null) {
				$warnings[] = 'Pro některou Portu pozici chybí historická hodnota.';
				continue;
			}

			$startDate ??= $startRecord->getDate();
			$endDate = $endRecord->getDate();
			$startValue += $startRecord->getCurrentValueAssetPrice()->getPrice();
			$endValue += $endRecord->getCurrentValueAssetPrice()->getPrice();
			$startInvested += $startRecord->getTotalInvestedAmountAssetPrice()->getPrice();
			$endInvested += $endRecord->getTotalInvestedAmountAssetPrice()->getPrice();
		}

		if ($startDate === null || $endDate === null) {
			return new PortfolioPeriodStatisticAssetDTO(
				$asset->getId()->toString(),
				'portu',
				$asset->getName(),
				null,
				$asset->getCurrency()->value,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				0.0,
				0.0,
				null,
				0.0,
				null,
				$warnings,
			);
		}

		$investedDifference = $endInvested - $startInvested;
		$purchases = max(0.0, $investedDifference);
		$sales = max(0.0, -$investedDifference);
		$startingCapital = $startValue + $investedDifference;
		$performance = $startingCapital !== 0.0 ? ($endValue / $startingCapital - 1) * 100 : null;

		$valueAtStartCzk = $this->convertSimpleValueToCzk(
			$startValue,
			$asset->getCurrency(),
			$startDate,
			$warnings,
		);
		$valueAtEndCzk = $this->convertSimpleValueToCzk(
			$endValue,
			$asset->getCurrency(),
			$endDate,
			$warnings,
		);
		$convertedPurchasesCzk = $this->convertSimpleValueToCzk(
			$purchases,
			$asset->getCurrency(),
			$endDate,
			$warnings,
		);
		$convertedSalesCzk = $this->convertSimpleValueToCzk(
			$sales,
			$asset->getCurrency(),
			$endDate,
			$warnings,
		);
		$capitalResult = $valueAtStartCzk !== null
			&& $valueAtEndCzk !== null
			&& $convertedPurchasesCzk !== null
			&& $convertedSalesCzk !== null
			? $valueAtEndCzk - $valueAtStartCzk - $convertedPurchasesCzk + $convertedSalesCzk
			: null;

		return new PortfolioPeriodStatisticAssetDTO(
			assetId: $asset->getId()->toString(),
			assetType: 'portu',
			name: $asset->getName(),
			ticker: null,
			currency: $asset->getCurrency()->value,
			priceStartDate: $startDate->format('Y-m-d'),
			priceEndDate: $endDate->format('Y-m-d'),
			priceAtStart: $startValue,
			priceAtEnd: $endValue,
			marketPerformancePercentage: $performance,
			valueAtStartCzk: $valueAtStartCzk,
			valueAtEndCzk: $valueAtEndCzk,
			purchasesCzk: $convertedPurchasesCzk ?? 0.0,
			salesCzk: $convertedSalesCzk ?? 0.0,
			capitalResultCzk: $capitalResult,
			netDividendsCzk: 0.0,
			totalContributionCzk: $capitalResult,
			warnings: array_values(array_unique($warnings)),
		);
	}

	private function buildChartSection(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		PortfolioPeriodStatisticDividendSectionDTO $dividendSection,
	): PortfolioPeriodStatisticChartSectionDTO
	{
		$dailyValues = [];
		foreach ($this->portfolioStatisticRecordRepository->findDailyChartValuesBetweenDates($start, $end) as $values) {
			$dailyValues[$values['date']->format('Y-m-d')] = $values;
		}

		$portfolioValues = [];
		$investedValues = [];
		foreach ($dailyValues as $date => $values) {
			$portfolioValues[] = new PortfolioPeriodStatisticChartPointDTO(
				$date,
				$this->normalizeRecordValue(
					$values['portfolioValue'],
					PortolioStatisticType::TOTAL_VALUE_IN_CZK,
				),
			);
			$investedValues[] = new PortfolioPeriodStatisticChartPointDTO(
				$date,
				$this->normalizeRecordValue(
					$values['investedValue'],
					PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
				),
			);
		}

		$dividendsByCompany = [];
		foreach ($dividendSection->dividends as $dividend) {
			if ($dividend->netAmountCzk === null) {
				continue;
			}

			$label = sprintf('%s (%s)', $dividend->stockAssetName, $dividend->ticker);
			$dividendsByCompany[$label] = ($dividendsByCompany[$label] ?? 0.0) + $dividend->netAmountCzk;
		}

		arsort($dividendsByCompany);

		$dividendPoints = [];
		foreach ($dividendsByCompany as $label => $value) {
			$dividendPoints[] = new PortfolioPeriodStatisticChartPointDTO($label, $value);
		}

		return new PortfolioPeriodStatisticChartSectionDTO(
			$portfolioValues,
			$investedValues,
			$dividendPoints,
		);
	}

	/**
	 * @return array{float, array<string>}
	 */
	private function calculateClosedPositionsProfit(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): array
	{
		$profit = 0.0;
		$warnings = [];
		$closedPositions = array_merge(
			$this->stockClosedPositionRepository->findBetweenDates($start, $end),
			$this->cryptoClosedPositionRepository->findBetweenDates($start, $end),
		);

		foreach ($closedPositions as $closedPosition) {
			$position = $closedPosition->getAssetPositon();
			try {
				$sellPrice = $this->currencyConversionFacade->getConvertedAssetPrice(
					$closedPosition->getTotalCloseAmountInBrokerCurrency(),
					CurrencyEnum::CZK,
					$closedPosition->getDate(),
				)->getPrice();
				$buyPrice = $this->currencyConversionFacade->getConvertedAssetPrice(
					$position->getTotalInvestedAmountInBrokerCurrency(),
					CurrencyEnum::CZK,
					$position->getOrderDate(),
				)->getPrice();
				$profit += $sellPrice - $buyPrice;
			} catch (MissingCurrencyPairException | NoResultException) {
				$warnings[] = sprintf(
					'Uzavřenou pozici %s nebylo možné převést do CZK.',
					$position->getAsset()->getName(),
				);
			}
		}

		return [$profit, $warnings];
	}

	private function getRecordValue(
		PortfolioStatisticRecord $record,
		PortolioStatisticType $type,
	): float
	{
		$value = $record->getPortfolioStatisticByType($type)?->getValue();
		if ($value === null) {
			throw new PortfolioPeriodStatisticUnableToBuildException(
				sprintf('Portfolio statistic snapshot is missing required value %s.', $type->value),
			);
		}

		return $this->normalizeRecordValue($value, $type);
	}

	private function normalizeRecordValue(string $value, PortolioStatisticType $type): float
	{
		$normalized = str_replace(['CZK', ' '], '', $value);
		if (!is_numeric($normalized)) {
			throw new PortfolioPeriodStatisticUnableToBuildException(
				sprintf('Portfolio statistic value %s is not numeric.', $type->value),
			);
		}

		return (float) $normalized;
	}

	/**
	 * @return array<string>
	 */
	private function buildRangeWarnings(
		PortfolioPeriodStatistic $report,
		ImmutableDateTime $effectiveStartAt,
		ImmutableDateTime $effectiveEndAt,
	): array
	{
		$warnings = [];
		if ($report->getRequestedStartAt()->format('Y-m-d') !== $effectiveStartAt->format('Y-m-d')) {
			$warnings[] = sprintf(
				'Začátek byl posunut na první dostupný snapshot %s.',
				$effectiveStartAt->format('Y-m-d'),
			);
		}

		if ($report->getRequestedEndAt()->format('Y-m-d') !== $effectiveEndAt->format('Y-m-d')) {
			$warnings[] = sprintf(
				'Konec byl posunut na poslední dostupný snapshot %s.',
				$effectiveEndAt->format('Y-m-d'),
			);
		}

		return $warnings;
	}

	private function positionIntersectsPeriod(
		AssetPosition $position,
		AssetClosedPosition|null $closedPosition,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): bool
	{
		return $position->getOrderDate() <= $end
			&& ($closedPosition === null || $closedPosition->getDate() >= $start);
	}

	private function positionActiveAt(
		AssetPosition $position,
		AssetClosedPosition|null $closedPosition,
		ImmutableDateTime $date,
	): bool
	{
		return $position->getOrderDate() <= $date
			&& ($closedPosition === null || $closedPosition->getDate() > $date);
	}

	private function isBetween(
		ImmutableDateTime $date,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): bool
	{
		return $date >= $start && $date <= $end;
	}

	/**
	 * @param array<string> $warnings
	 */
	private function calculateBoundaryValue(
		Asset $asset,
		AssetPriceRecord|null $priceRecord,
		float $pieces,
		array &$warnings,
	): float|null
	{
		if ($pieces === 0.0) {
			return 0.0;
		}

		if ($priceRecord === null) {
			$warnings[] = 'Chybí cena pro výpočet historické hodnoty pozice.';
			return null;
		}

		$price = $priceRecord->getAssetPrice();
		return $this->convertSimpleValueToCzk(
			$price->getPrice() * $pieces,
			$price->getCurrency(),
			$priceRecord->getDate(),
			$warnings,
		);
	}

	/**
	 * @param array<string> $warnings
	 */
	private function convertAssetPriceToCzk(
		AssetPrice $price,
		ImmutableDateTime $date,
		array &$warnings,
	): float|null
	{
		try {
			return $this->currencyConversionFacade->getConvertedAssetPrice(
				$price,
				CurrencyEnum::CZK,
				$date,
			)->getPrice();
		} catch (MissingCurrencyPairException | NoResultException) {
			$warnings[] = sprintf('Chybí historický měnový kurz pro datum %s.', $date->format('Y-m-d'));
			return null;
		}
	}

	/**
	 * @param array<string> $warnings
	 */
	private function convertSimpleValueToCzk(
		float $value,
		CurrencyEnum $currency,
		ImmutableDateTime $date,
		array &$warnings,
	): float|null
	{
		if ($currency === CurrencyEnum::CZK) {
			return $value;
		}

		try {
			return $this->currencyConversionFacade->convertSimpleValue(
				$value,
				$currency,
				CurrencyEnum::CZK,
				$date,
			);
		} catch (MissingCurrencyPairException | NoResultException) {
			$warnings[] = sprintf('Chybí historický měnový kurz pro datum %s.', $date->format('Y-m-d'));
			return null;
		}
	}

}
