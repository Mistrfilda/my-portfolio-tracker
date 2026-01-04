<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Asset\Price\SummaryPrice;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
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
	)
	{
	}

	public function recalculateAll(): void
	{
		$this->logger->debug('Recalculating all forecasts');
		$now = $this->datetimeFactory->createNow();
		foreach ($this->stockAssetDividendForecastRepository->findAllActive($now->getYear()) as $stockAssetForecast) {
			$this->recalculate($stockAssetForecast->getId());
		}

		$this->logger->debug('All forecasts recalculated');
	}

	public function recalculate(UuidInterface $stockAssetForecastId): void
	{
		$stockAssetForecast = $this->stockAssetDividendForecastRepository->getById($stockAssetForecastId);

		$existingRecords = $this->stockAssetDividendForecastRecordRepository->findByStockAssetDividendForecast(
			$stockAssetForecastId,
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
			if ($stockAsset->hasOpenPositions() === false || $stockAsset->doesPaysDividends() === false) {
				if (array_key_exists($stockAsset->getId()->toString(), $existingRecordsByStockAsset)) {
					$recordsToDelete[] = $existingRecordsByStockAsset[$stockAsset->getId()->toString()];
				}

				continue;
			}

			$previousDividends = $this->stockAssetDividendRepository->findByStockAssetForYear(
				$stockAsset,
				$dividendYearForTotalCalculations,
			);

			$previousYearDividendsByMonth = [];
			$previousYearTotalPrice = new SummaryPrice($stockAsset->getCurrency());

			$specialDividendsTotalPrice = new SummaryPrice($stockAsset->getCurrency());

			foreach ($previousDividends as $previousYearDividend) {
				if ($previousYearDividend->getDividendType() === StockAssetDividendTypeEnum::REGULAR) {
					$previousYearDividendsByMonth[$previousYearDividend->getExDate()->getMonth()] = $previousYearDividend;
					$previousYearTotalPrice->addSummaryPrice($previousYearDividend->getSummaryPrice());
				} else {
					$specialDividendsTotalPrice->addSummaryPrice($previousYearDividend->getSummaryPrice());
				}
			}

			$stockAssetForecastYearReceivedDividends = $this->stockAssetDividendRepository->findByStockAssetForYear(
				$stockAsset,
				$forYear,
			);

			$lastDividendForYear = null;
			$receivedDividendsForYear = [];
			$receivedTotalPriceForYear = new SummaryPrice($stockAsset->getCurrency());
			$specialDividendsTotalPriceForYear = new SummaryPrice($stockAsset->getCurrency());

			foreach ($stockAssetForecastYearReceivedDividends as $stockAssetForecastYearReceivedDividend) {
				if ($stockAssetForecastYearReceivedDividend->getDividendType() === StockAssetDividendTypeEnum::REGULAR) {
					$lastDividendForYear = $stockAssetForecastYearReceivedDividend;
					$receivedDividendsForYear[$stockAssetForecastYearReceivedDividend
						->getExDate()->getMonth()] = $stockAssetForecastYearReceivedDividend;
					$receivedTotalPriceForYear->addSummaryPrice(
						$stockAssetForecastYearReceivedDividend->getSummaryPrice(),
					);
				} else {
					$specialDividendsTotalPriceForYear->addSummaryPrice(
						$stockAssetForecastYearReceivedDividend->getSummaryPrice(),
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
			if ($stockAssetForecast->getTrend()->getTrendNumber() !== 0) {
				$trendPercentage = $stockAssetForecast->getTrend()->getTrendNumber();
				$multiplier = 1 + ($trendPercentage / 100);
				$adjustedPrice *= $multiplier;
			}

			$dividendUsuallyPaidAtMonths = array_keys($previousYearDividendsByMonth);
			$receivedDividendMonths = array_keys($receivedDividendsForYear);

			$expectedDividendPerStock = 0;
			$expectedDividendsCount = count($dividendUsuallyPaidAtMonths) - count($receivedDividendMonths);
			if ($expectedDividendsCount > 0) {
				$expectedDividendPerStock = $adjustedPrice * $expectedDividendsCount;
			}

			$brokerCurrency = $stockAsset->getCurrency();
			$alreadyReceivedConverted = $receivedTotalPriceForYear->getPrice();
			$originalDividendConverted = $usedDividendForCalculation->getSummaryPrice()->getPrice();
			$adjustedPriceConverted = $adjustedPrice;
			$expectedDividendPerStockConverted = $expectedDividendPerStock;
			$specialDividendsConverted = $specialDividendsTotalPriceForYear->getPrice();

			if (array_key_exists($stockAsset->getId()->toString(), $existingRecordsByStockAsset)) {
				$existingRecord = $existingRecordsByStockAsset[$stockAsset->getId()->toString()];
				$existingRecord->recalculate(
					$receivedDividendMonths,
					$alreadyReceivedConverted,
					$dividendUsuallyPaidAtMonths,
					$stockAsset->getTotalPiecesHeld(),
					$originalDividendConverted,
					$adjustedPriceConverted,
					$expectedDividendPerStockConverted,
					null,
					$specialDividendsConverted,
				);
			} else {
				$forecastRecords = new StockAssetDividendForecastRecord(
					$stockAssetForecast,
					$stockAsset,
					$brokerCurrency,
					$dividendUsuallyPaidAtMonths,
					$receivedDividendMonths,
					$alreadyReceivedConverted,
					$stockAsset->getTotalPiecesHeld(),
					$originalDividendConverted,
					$adjustedPriceConverted,
					$expectedDividendPerStockConverted,
					null,
					$specialDividendsConverted,
					$this->datetimeFactory->createNow(),
				);
				$this->entityManager->persist($forecastRecords);
			}
		}

		$this->entityManager->flush();

		foreach ($recordsToDelete as $recordToDelete) {
			$this->entityManager->remove($recordToDelete);
		}

		$stockAssetForecast->recalculated($this->datetimeFactory->createNow());
		$this->entityManager->flush();
	}

	public function updateCustomValuesForRecord(
		UuidInterface $id,
		float|null $customDividendUsedForCalculation,
		float|null $expectedSpecialDividendThisYearPerStock,
	): void
	{
		$record = $this->stockAssetDividendForecastRecordRepository->getById($id);
		$record->setCustomValues($customDividendUsedForCalculation, $expectedSpecialDividendThisYearPerStock);
		$record->getStockAssetDividendForecast()->recalculatingPending();
		$this->entityManager->flush();
	}

}
