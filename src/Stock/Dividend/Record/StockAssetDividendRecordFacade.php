<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;

class StockAssetDividendRecordFacade
{

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendRecordService $stockAssetDividendRecordService,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private CurrencyConversionFacade $currencyConversionFacade,
		private LoggerInterface $logger,
	)
	{
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function processAllDividends(): array
	{
		$dividendPayers = $this->stockAssetRepository->findDividendPayers();

		$processedDividendRecords = [];
		foreach ($dividendPayers as $dividendPayer) {
			$this->logger->debug(
				sprintf('Processing dividend payer %s', $dividendPayer->getName()),
			);

			$dividendRecords = $this->stockAssetDividendRecordService->processDividendRecords(
				new ArrayCollection($this->stockAssetDividendRepository->findByStockAsset(
					$dividendPayer,
				)),
				new ArrayCollection($dividendPayer->getPositions()),
			);

			$processedDividendRecords = array_merge($processedDividendRecords, $dividendRecords->toArray());

			foreach ($dividendRecords as $dividendRecord) {
				$existingRow = $this->stockAssetDividendRecordRepository->findOneByStockDividend(
					$dividendRecord->getStockAssetDividend(),
				);

				if ($existingRow !== null) {
					$existingRow->update(
						$dividendRecord->getTotalPiecesHeldAtExDate(),
						$dividendRecord->getTotalAmount(),
						$dividendRecord->getCurrency(),
						$dividendRecord->getTotalAmountInBrokerCurrency(),
						$dividendRecord->getBrokerCurrency(),
						$this->datetimeFactory->createNow(),
					);

					continue;
				}

				$this->entityManager->persist($dividendRecord);
			}

			$this->entityManager->flush();
		}

		return $processedDividendRecords;
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function getLastYearDividendRecordsForDashboard(): array
	{
		return $this->stockAssetDividendRecordRepository->findGreaterThan(
			$this->datetimeFactory->createNow()->deductYearsFromDatetime(1),
			15,
		);
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function getLastDividends(int $limit): array
	{
		return $this->stockAssetDividendRecordRepository->findLastDividendRecords($limit);
	}

	/**
	 * @return array<StockAssetDividendYearSummaryDTO>
	 */
	public function getDividendsByYears(): array
	{
		$records = $this->stockAssetDividendRecordRepository->findAllForMonthChart();
		/** @var array<int, StockAssetDividendYearSummaryDTO> $preparedData */
		$preparedData = [];

		foreach ($records as $record) {
			$recordPriceWithoutTax = $record->getSummaryPrice();
			if ($recordPriceWithoutTax->getCurrency() !== CurrencyEnum::CZK) {
				$recordPriceWithoutTax = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPriceWithoutTax,
					CurrencyEnum::CZK,
					$record->getStockAssetDividend()->getExDate(),
				);
			}

			$recordPriceWitTax = $record->getSummaryPrice(false);
			if ($recordPriceWitTax->getCurrency() !== CurrencyEnum::CZK) {
				$recordPriceWitTax = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPriceWitTax,
					CurrencyEnum::CZK,
					$record->getStockAssetDividend()->getExDate(),
				);
			}

			$key = (int) $record->getStockAssetDividend()->getExDate()->format('Y');
			if (array_key_exists($key, $preparedData)) {
				$preparedData[$key]->getSummaryPriceWithoutTax()->addSummaryPrice($recordPriceWithoutTax);
				$preparedData[$key]->getSummaryPriceWithTax()->addSummaryPrice($recordPriceWitTax);
			} else {
				$preparedData[$key] = new StockAssetDividendYearSummaryDTO(
					$key,
					$recordPriceWithoutTax,
					$recordPriceWitTax,
				);
			}
		}

		krsort($preparedData);

		return $preparedData;
	}

	public function getTotalSummaryPrice(bool $reinvestedOnly = true): SummaryPrice
	{
		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		foreach ($this->stockAssetDividendRecordRepository->findAll() as $stockAssetDividendRecord) {
			if ($reinvestedOnly && $stockAssetDividendRecord->isReinvested() === false) {
				continue;
			}

			$recordPrice = $stockAssetDividendRecord->getSummaryPrice();
			if ($recordPrice->getCurrency() !== $totalSummaryPrice->getCurrency()) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$stockAssetDividendRecord->getStockAssetDividend()->getExDate(),
				);
			}

			$totalSummaryPrice->addSummaryPrice($recordPrice);
		}

		return $totalSummaryPrice;
	}

	public function getTotalSummaryPriceForStockAsset(StockAsset $stockAsset): SummaryPrice|null
	{
		$dividendRecords = $this->stockAssetDividendRecordRepository->findByStockAsset($stockAsset);
		if (count($dividendRecords) === 0) {
			return null;
		}

		$summaryPrice = new SummaryPrice($stockAsset->getBrokerDividendCurrency() ?? $stockAsset->getCurrency());
		foreach ($dividendRecords as $dividendRecord) {
			$summaryPrice->addSummaryPrice(
				$this->currencyConversionFacade->getConvertedSummaryPrice(
					$dividendRecord->getSummaryPrice(),
					$summaryPrice->getCurrency(),
					$dividendRecord->getCreatedAt(),
				),
			);
		}

		return $summaryPrice;
	}

}
