<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\Exception\StockAssetTickerAlreadyExistsException;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class StockAssetFacade
{

	public function __construct(
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function create(
		string $name,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		string $ticker,
		StockAssetExchange $exchange,
		CurrencyEnum $currency,
	): StockAsset
	{
		if ($this->stockAssetRepository->findByTicker($ticker) !== null) {
			throw new StockAssetTickerAlreadyExistsException();
		}

		$stockAsset = new StockAsset(
			$name,
			$assetPriceDownloader,
			$ticker,
			$exchange,
			$currency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($stockAsset);
		$this->entityManager->flush();

		$this->logger->info(
			sprintf(
				'User %s create new stock asset %s - %s',
				$this->currentAppAdminGetter->getAppAdmin()->getName(),
				$stockAsset->getName(),
				$stockAsset->getId()->toString(),
			),
		);

		return $stockAsset;
	}

	public function update(
		UuidInterface $id,
		string $name,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		string $ticker,
		StockAssetExchange $exchange,
		CurrencyEnum $currency,
	): StockAsset
	{
		$stockAsset = $this->stockAssetRepository->getById($id);
		$stockAsset->update(
			$name,
			$assetPriceDownloader,
			$ticker,
			$exchange,
			$currency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();

		$this->logger->info(
			sprintf(
				'User %s updated stock asset %s - %s',
				$this->currentAppAdminGetter->getAppAdmin()->getName(),
				$stockAsset->getName(),
				$stockAsset->getId()->toString(),
			),
		);

		return $stockAsset;
	}

}
