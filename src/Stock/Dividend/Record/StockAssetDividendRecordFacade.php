<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Position\StockPositionRepository;
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
		private JobRequestFacade $jobRequestFacade,
		private AppAdminRepository $appAdminRepository,
		private StockPositionRepository $stockPositionRepository,
	)
	{
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function processAllDividends(): array
	{
		$records = [];
		foreach ($this->appAdminRepository->findAll() as $appAdmin) {
			$records = array_merge($records, $this->processForAppAdmin($appAdmin));
		}

		return $records;
	}

	/** @return array<StockAssetDividendRecord> */
	public function processForAppAdmin(AppAdmin $appAdmin): array
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
				new ArrayCollection(
					$this->stockPositionRepository->findByAssetForAppAdmin($dividendPayer->getId(), $appAdmin),
				),
				$appAdmin,
			);

			$processedDividendRecords = array_merge($processedDividendRecords, $dividendRecords->toArray());

			foreach ($dividendRecords as $dividendRecord) {
				$existingRow = $this->stockAssetDividendRecordRepository->findOneByStockDividend(
					$dividendRecord->getStockAssetDividend(),
					$appAdmin,
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
				$dividendRecord->getStockAssetDividend()->addRecord($dividendRecord);
			}

			$this->entityManager->flush();
		}

		$this->jobRequestFacade->addToQueue(
			JobRequestTypeEnum::STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE_ALL,
			['appAdminId' => $appAdmin->getId()->toString()],
		);
		return $processedDividendRecords;
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function getLastYearDividendRecordsForDashboard(AppAdmin|null $appAdmin = null): array
	{
		return $this->stockAssetDividendRecordRepository->findGreaterThan(
			$this->datetimeFactory->createNow()->deductYearsFromDatetime(1),
			15,
			$appAdmin,
		);
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function getLastDividends(int $limit, AppAdmin|null $appAdmin = null): array
	{
		return $this->stockAssetDividendRecordRepository->findLastDividendRecords($limit, $appAdmin);
	}

	/**
	 * @return array<StockAssetDividendYearSummaryDTO>
	 */
	public function getDividendsByYears(AppAdmin|null $appAdmin = null): array
	{
		$records = $this->stockAssetDividendRecordRepository->findAllForMonthChart($appAdmin);
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

	public function getTotalSummaryPrice(bool $reinvestedOnly = true, AppAdmin|null $appAdmin = null): SummaryPrice
	{
		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		foreach ($this->stockAssetDividendRecordRepository->findAll($appAdmin) as $stockAssetDividendRecord) {
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

	public function getTotalSummaryPriceForStockAsset(
		StockAsset $stockAsset,
		AppAdmin|null $appAdmin = null,
	): SummaryPrice|null
	{
		$dividendRecords = $this->stockAssetDividendRecordRepository->findByStockAsset($stockAsset, $appAdmin);
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
