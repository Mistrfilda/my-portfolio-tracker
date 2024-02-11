<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

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

}
