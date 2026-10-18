<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Asset\Price\SummaryPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Position\StockPositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendForecastRecordFacade
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetDividendForecastRepository $stockAssetDividendForecastRepository,
		private StockAssetDividendForecastRecordRepository $stockAssetDividendForecastRecordRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private LoggerInterface $logger,
		private StockPositionRepository $stockPositionRepository,
		private AppAdminRepository $appAdminRepository,
	)
	{
	}

	public function recalculateAll(UuidInterface|null $appAdminId = null): void
	{
		$this->logger->debug('Recalculating all forecasts');
		$now = $this->datetimeFactory->createNow();
		// Legacy queue jobs without an owner still process each account separately.
		$owners = $appAdminId === null
			? $this->appAdminRepository->findAll()
			: [$this->appAdminRepository->findById($appAdminId)];
		foreach ($owners as $owner) {
			foreach ($this->stockAssetDividendForecastRepository->findAllActive(
				$now->getYear(),
				$owner,
			) as $stockAssetForecast) {
				$this->recalculate($stockAssetForecast->getId());
			}
		}

		$this->logger->debug('All forecasts recalculated');
	}

	public function recalculate(UuidInterface $stockAssetForecastId): void
	{
		$stockAssetForecast = $this->stockAssetDividendForecastRepository->getByIdForProcessing($stockAssetForecastId);

		$appAdmin = $stockAssetForecast->getAppAdmin();

		$existingRecords = $this->stockAssetDividendForecastRecordRepository->findByStockAssetDividendForecast(
			$stockAssetForecastId,
			$appAdmin,
		);

		$existingRecordsByStockAsset = [];
		$recordsToDelete = [];

		foreach ($existingRecords as $existingRecord) {
			$existingRecordsByStockAsset[$existingRecord->getStockAsset()->getId()->toString()] = $existingRecord;
		}

		$forYear = $stockAssetForecast->getForYear();
		$dividendYearForTotalCalculations = $forYear - 1;
		if ($this->datetimeFactory->createNow()->getYear() === $dividendYearForTotalCalculations) {
			$dividendYearForTotalCalculations--;
		}

		foreach ($this->stockAssetRepository->findAll() as $stockAsset) {
			$stockAssetForecastYearReceivedDividends = $this->stockAssetDividendRepository->findByStockAssetForYear(
				$stockAsset,
				$forYear,
			);
			$piecesCurrentlyHeld = $this->getOpenPiecesCurrentlyHeld($stockAsset, $appAdmin);
			$hasOpenDividendPayingPosition = $piecesCurrentlyHeld > 0 && $stockAsset->doesPaysDividends();
			$hasActualDividendRecord = $this->hasActualDividendRecordForYear(
				$stockAssetForecastYearReceivedDividends,
				$appAdmin,
			);

			if ($hasOpenDividendPayingPosition === false && $hasActualDividendRecord === false) {
				if (array_key_exists($stockAsset->getId()->toString(), $existingRecordsByStockAsset)) {
					$recordsToDelete[] = $existingRecordsByStockAsset[$stockAsset->getId()->toString()];
				}

				continue;
			}

			$existingRecord = $existingRecordsByStockAsset[$stockAsset->getId()->toString()] ?? null;
			$customDividendUsedForCalculation = $existingRecord?->getCustomDividendUsedForCalculation();
			$expectedSpecialDividendThisYearPerStock = $existingRecord?->getExpectedSpecialDividendThisYearPerStock();
			$expectedSpecialDividendThisYearPerStockBeforeTax = $existingRecord?->getExpectedSpecialDividendThisYearPerStockBeforeTax();

			$previousDividends = $this->stockAssetDividendRepository->findByStockAssetForYear(
				$stockAsset,
				$dividendYearForTotalCalculations,
			);

			$previousYearDividendsByMonth = [];
			$previousYearTotalPrice = new SummaryPrice($stockAsset->getCurrency());

			$specialDividendsTotalPrice = new SummaryPrice($stockAsset->getCurrency());
			$specialDividendsTotalPriceBeforeTax = new SummaryPrice($stockAsset->getCurrency());

			foreach ($previousDividends as $previousYearDividend) {
				if ($previousYearDividend->getDividendType() === StockAssetDividendTypeEnum::REGULAR) {
					$previousYearDividendsByMonth[$previousYearDividend->getExDate()->getMonth()] = $previousYearDividend;
					$previousYearTotalPrice->addSummaryPrice($previousYearDividend->getSummaryPrice());
				} else {
					$specialDividendsTotalPrice->addSummaryPrice($previousYearDividend->getSummaryPrice());
					$specialDividendsTotalPriceBeforeTax->addSummaryPrice(
						$previousYearDividend->getSummaryPrice(false),
					);
				}
			}

			$dividendTax = $stockAsset->getDividendTax();

			$lastDividendForYear = null;
			$receivedDividendsForYear = [];
			$existingRegularDividendMonths = [];
			$receivedTotalPriceForYear = new SummaryPrice($stockAsset->getCurrency());
			$receivedTotalPriceForYearBeforeTax = new SummaryPrice($stockAsset->getCurrency());
			$specialDividendsTotalPriceForYear = new SummaryPrice($stockAsset->getCurrency());
			$specialDividendsTotalPriceForYearBeforeTax = new SummaryPrice($stockAsset->getCurrency());

			foreach ($stockAssetForecastYearReceivedDividends as $stockAssetForecastYearReceivedDividend) {
				if ($stockAssetForecastYearReceivedDividend->getDividendType() === StockAssetDividendTypeEnum::REGULAR) {
					$existingRegularDividendMonths[] = $stockAssetForecastYearReceivedDividend->getExDate()->getMonth();
				}

				if ($this->hasActualDividendRecord($stockAssetForecastYearReceivedDividend, $appAdmin) === false) {
					continue;
				}

				if ($stockAssetForecastYearReceivedDividend->getDividendType() === StockAssetDividendTypeEnum::REGULAR) {
					$lastDividendForYear = $stockAssetForecastYearReceivedDividend;
					$receivedDividendsForYear[$stockAssetForecastYearReceivedDividend
						->getExDate()->getMonth()] = $stockAssetForecastYearReceivedDividend;
					$receivedTotalPriceForYear->addSummaryPrice(
						$stockAssetForecastYearReceivedDividend->getSummaryPrice(),
					);
					$receivedTotalPriceForYearBeforeTax->addSummaryPrice(
						$stockAssetForecastYearReceivedDividend->getSummaryPrice(false),
					);
				} else {
					$specialDividendsTotalPriceForYear->addSummaryPrice(
						$stockAssetForecastYearReceivedDividend->getSummaryPrice(),
					);
					$specialDividendsTotalPriceForYearBeforeTax->addSummaryPrice(
						$stockAssetForecastYearReceivedDividend->getSummaryPrice(false),
					);
				}
			}

			$usedDividendForCalculation = $lastDividendForYear;
			if ($usedDividendForCalculation === null) {
				$lastDividend = $this->stockAssetDividendRepository->getLastDividend(
					$stockAsset,
					StockAssetDividendTypeEnum::REGULAR,
				);
				if ($lastDividend === null) {
					break;
				}

				$usedDividendForCalculation = $lastDividend;
			}

			$adjustedPrice = $usedDividendForCalculation->getSummaryPrice()->getPrice();
			$adjustedPriceBeforeTax = $usedDividendForCalculation->getSummaryPrice(false)->getPrice();
			if ($stockAssetForecast->getTrend()->getTrendNumber() !== 0) {
				$trendPercentage = $stockAssetForecast->getTrend()->getTrendNumber();
				$multiplier = 1 + ($trendPercentage / 100);
				$adjustedPrice *= $multiplier;
				$adjustedPriceBeforeTax *= $multiplier;
			}

			$dividendForCalculation = $customDividendUsedForCalculation ?? $adjustedPrice;
			$customGrossDividendUsedForCalculation = $existingRecord?->getCustomGrossDividendUsedForCalculation();
			$dividendForCalculationBeforeTax = $customGrossDividendUsedForCalculation ?? $adjustedPriceBeforeTax;
			if ($customDividendUsedForCalculation !== null && $customGrossDividendUsedForCalculation === null) {
				$dividendForCalculationBeforeTax = $dividendTax !== null
					? $customDividendUsedForCalculation / (1 - ($dividendTax * 0.01))
					: $customDividendUsedForCalculation;
			}

			$dividendUsuallyPaidAtMonths = array_keys($previousYearDividendsByMonth);
			$receivedDividendMonths = array_keys($receivedDividendsForYear);
			$expectedDividendMonths = array_diff(
				$dividendUsuallyPaidAtMonths,
				$existingRegularDividendMonths,
			);

			$expectedDividendPerStock = 0;
			$expectedDividendPerStockBeforeTax = 0;
			$expectedDividendsCount = count($expectedDividendMonths);
			if ($expectedDividendsCount > 0) {
				$expectedDividendPerStock = $dividendForCalculation * $expectedDividendsCount;
				$expectedDividendPerStockBeforeTax = $dividendForCalculationBeforeTax * $expectedDividendsCount;
			}

			if ($expectedSpecialDividendThisYearPerStock !== null) {
				$alreadyReceivedSpecialDividendPerStock = $specialDividendsTotalPriceForYear->getPrice();
				$remainingSpecialDividend = $expectedSpecialDividendThisYearPerStock - $alreadyReceivedSpecialDividendPerStock;
				if ($remainingSpecialDividend > 0) {
					$expectedDividendPerStock += $remainingSpecialDividend;
				}

				$expectedSpecialBeforeTax = $expectedSpecialDividendThisYearPerStockBeforeTax;
				if ($expectedSpecialBeforeTax === null) {
					$expectedSpecialBeforeTax = $dividendTax !== null
						? $expectedSpecialDividendThisYearPerStock / (1 - ($dividendTax * 0.01))
						: $expectedSpecialDividendThisYearPerStock;
				}

				$alreadyReceivedSpecialDividendPerStockBeforeTax = $specialDividendsTotalPriceForYearBeforeTax->getPrice();
				$remainingSpecialDividendBeforeTax = $expectedSpecialBeforeTax - $alreadyReceivedSpecialDividendPerStockBeforeTax;
				if ($remainingSpecialDividendBeforeTax > 0) {
					$expectedDividendPerStockBeforeTax += $remainingSpecialDividendBeforeTax;
				}
			}

			$brokerCurrency = $stockAsset->getCurrency();
			$alreadyReceivedConverted = $receivedTotalPriceForYear->getPrice();
			$alreadyReceivedConvertedBeforeTax = $receivedTotalPriceForYearBeforeTax->getPrice();
			$originalDividendConverted = $usedDividendForCalculation->getSummaryPrice()->getPrice();
			$originalDividendConvertedBeforeTax = $usedDividendForCalculation->getSummaryPrice(false)->getPrice();
			$adjustedPriceConverted = $adjustedPrice;
			$adjustedPriceConvertedBeforeTax = $adjustedPriceBeforeTax;
			$expectedDividendPerStockConverted = $expectedDividendPerStock;
			$expectedDividendPerStockConvertedBeforeTax = $expectedDividendPerStockBeforeTax;
			$specialDividendsConverted = $specialDividendsTotalPriceForYear->getPrice();
			$specialDividendsConvertedBeforeTax = $specialDividendsTotalPriceForYearBeforeTax->getPrice();

			if ($existingRecord !== null) {
				$existingRecord->recalculate(
					$receivedDividendMonths,
					$alreadyReceivedConverted,
					$alreadyReceivedConvertedBeforeTax,
					$dividendUsuallyPaidAtMonths,
					$piecesCurrentlyHeld,
					$originalDividendConverted,
					$originalDividendConvertedBeforeTax,
					$adjustedPriceConverted,
					$adjustedPriceConvertedBeforeTax,
					$expectedDividendPerStockConverted,
					$expectedDividendPerStockConvertedBeforeTax,
					$customDividendUsedForCalculation,
					$customGrossDividendUsedForCalculation,
					$specialDividendsConverted,
					$specialDividendsConvertedBeforeTax,
				);
			} else {
				$forecastRecords = new StockAssetDividendForecastRecord(
					$stockAssetForecast,
					$stockAsset,
					$brokerCurrency,
					$dividendUsuallyPaidAtMonths,
					$receivedDividendMonths,
					$alreadyReceivedConverted,
					$alreadyReceivedConvertedBeforeTax,
					$piecesCurrentlyHeld,
					$originalDividendConverted,
					$originalDividendConvertedBeforeTax,
					$adjustedPriceConverted,
					$adjustedPriceConvertedBeforeTax,
					$expectedDividendPerStockConverted,
					$expectedDividendPerStockConvertedBeforeTax,
					null,
					null,
					$specialDividendsConverted,
					$specialDividendsConvertedBeforeTax,
					$this->datetimeFactory->createNow(),
				);
				$this->entityManager->persist($forecastRecords);
			}
		}

		$this->entityManager->flush();

		foreach ($recordsToDelete as $recordToDelete) {
			$this->entityManager->remove($recordToDelete);
		}

		$this->entityManager->refresh($stockAssetForecast);
		$stockAssetForecast->recalculated($this->datetimeFactory->createNow());
		$this->entityManager->flush();
	}

	public function updateCustomValuesForRecord(
		UuidInterface $id,
		float|null $customDividendUsedForCalculation,
		float|null $customGrossDividendUsedForCalculation,
		float|null $expectedSpecialDividendThisYearPerStock,
		float|null $expectedSpecialDividendThisYearPerStockBeforeTax,
	): void
	{
		$record = $this->stockAssetDividendForecastRecordRepository->getById($id);
		$dividendTax = $record->getStockAsset()->getDividendTax();

		if ($customGrossDividendUsedForCalculation !== null && $customDividendUsedForCalculation === null) {
			$customDividendUsedForCalculation = $dividendTax !== null
				? $customGrossDividendUsedForCalculation * (1 - ($dividendTax * 0.01))
				: $customGrossDividendUsedForCalculation;
		}

		if (
			$expectedSpecialDividendThisYearPerStockBeforeTax !== null
			&& $expectedSpecialDividendThisYearPerStock === null
		) {
			$expectedSpecialDividendThisYearPerStock = $dividendTax !== null
				? $expectedSpecialDividendThisYearPerStockBeforeTax * (1 - ($dividendTax * 0.01))
				: $expectedSpecialDividendThisYearPerStockBeforeTax;
		}

		if (
			$expectedSpecialDividendThisYearPerStock !== null
			&& $expectedSpecialDividendThisYearPerStockBeforeTax === null
		) {
			$expectedSpecialDividendThisYearPerStockBeforeTax = $dividendTax !== null
				? $expectedSpecialDividendThisYearPerStock / (1 - ($dividendTax * 0.01))
				: $expectedSpecialDividendThisYearPerStock;
		}

		$record->setCustomValues(
			$customDividendUsedForCalculation,
			$customGrossDividendUsedForCalculation,
			$expectedSpecialDividendThisYearPerStock,
			$expectedSpecialDividendThisYearPerStockBeforeTax,
		);
		$record->getStockAssetDividendForecast()->recalculatingPending();
		$this->entityManager->flush();
	}

	/**
	 * @param array<StockAssetDividend> $dividends
	 */
	private function hasActualDividendRecordForYear(array $dividends, AppAdmin $appAdmin): bool
	{
		foreach ($dividends as $dividend) {
			if ($this->hasActualDividendRecord($dividend, $appAdmin)) {
				return true;
			}
		}

		return false;
	}

	private function hasActualDividendRecord(StockAssetDividend $dividend, AppAdmin $appAdmin): bool
	{
		return count($dividend->getRecordsForAppAdmin($appAdmin)) > 0;
	}

	private function getOpenPiecesCurrentlyHeld(StockAsset $stockAsset, AppAdmin $appAdmin): int
	{
		$total = 0;
		foreach ($this->stockPositionRepository->findByAssetForAppAdmin(
			$stockAsset->getId(),
			$appAdmin,
			true,
		) as $position) {
			$total += $position->getOrderPiecesCount();
		}

		return $total;
	}

}
