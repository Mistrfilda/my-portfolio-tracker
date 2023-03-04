<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class StockAssetDividendRecordFacade
{

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendRecordService $stockAssetDividendRecordService,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function processAllDividends(): void
	{
		$dividendPayers = $this->stockAssetRepository->findDividendPayers();

		foreach ($dividendPayers as $dividendPayer) {
			$dividendRecords = $this->stockAssetDividendRecordService->processDividendRecords(
				new ArrayCollection($this->stockAssetDividendRepository->findByStockAsset(
					$dividendPayer,
				)),
				new ArrayCollection($dividendPayer->getPositions()),
			);

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
	}

}
