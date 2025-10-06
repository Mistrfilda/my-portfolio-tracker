<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\Exception\StockAssetTickerAlreadyExistsException;
use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StockAssetFacade
{

	public function __construct(
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly StockAssetIndustryRepository $stockAssetIndustryRepository,
	)
	{
	}

	public function create(
		string $name,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		string $ticker,
		StockAssetExchange $exchange,
		CurrencyEnum $currency,
		string|null $isin,
		StockAssetDividendSourceEnum|null $stockAssetDividendSource,
		float|null $dividendTax,
		CurrencyEnum|null $brokerDividendCurrency,
		bool $shouldDownloadPrice,
		bool $shouldDownloadValuation,
		string|null $industryId,
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
			isin: $isin,
			stockAssetDividendSource: $stockAssetDividendSource,
			dividendTax: $dividendTax,
			brokerDividendCurrency: $brokerDividendCurrency,
			shouldDownloadPrice: $shouldDownloadPrice,
			shouldDownloadValuation: $shouldDownloadValuation,
			industry: $industryId !== null ? $this->stockAssetIndustryRepository->getById(
				Uuid::fromString($industryId),
			) : null,
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
		string|null $isin,
		StockAssetDividendSourceEnum|null $stockAssetDividendSource,
		float|null $dividendTax,
		CurrencyEnum|null $brokerDividendCurrency,
		bool $shouldDownloadPrice,
		bool $shouldDownloadValuation,
		string|null $industryId,
	): StockAsset
	{
		$stockAsset = $this->stockAssetRepository->getById($id);
		$stockAsset->update(
			$name,
			$assetPriceDownloader,
			$ticker,
			$exchange,
			$currency,
			isin: $isin,
			now: $this->datetimeFactory->createNow(),
			dividendTax: $dividendTax,
			stockAssetDividendSource: $stockAssetDividendSource,
			brokerDividendCurrency: $brokerDividendCurrency,
			shouldDownloadPrice: $shouldDownloadPrice,
			shouldDownloadValuation: $shouldDownloadValuation,
			industry: $industryId !== null ? $this->stockAssetIndustryRepository->getById(
				Uuid::fromString($industryId),
			) : null,
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
