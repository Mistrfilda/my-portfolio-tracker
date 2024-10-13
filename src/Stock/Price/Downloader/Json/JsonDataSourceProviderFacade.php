<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json;

use App\Asset\Price\AssetPriceSourceProvider;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\StockAssetDividendSourceProvider;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;

class JsonDataSourceProviderFacade implements AssetPriceSourceProvider, StockAssetDividendSourceProvider
{

	public const STOCK_ASSET_PRICE_FILENAME = 'prices.json';

	public const STOCK_ASSET_DIVIDENDS_FILENAME = 'dividends.json';

	public function __construct(
		private readonly int $updateStockAssetHoursThreshold,
		private StockAssetRepository $stockAssetRepository,
		private DatetimeFactory $datetimeFactory,
		private JsonWebDataService $jsonWebDataService,
	)
	{

	}

	public function generatePriceSourcesJsonFile(string $fileLocation): void
	{
		$stockAssets = $this->stockAssetRepository->findAllByAssetPriceDownloader(
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			priceDownloadedBefore: $this->datetimeFactory->createNow()->deductHoursFromDatetime(
				$this->updateStockAssetHoursThreshold,
			),
		);

		$stockAssetsToDownload = [];
		foreach ($stockAssets as $stockAsset) {
			$stockAssetsToDownload[] = [
				'id' => $stockAsset->getId()->toString(),
				'name' => $stockAsset->getName(),
				'currency' => $stockAsset->getCurrency()->value,
				'url' => $this->jsonWebDataService->getStockAssetPriceUrl($stockAsset),
			];
		}

		FileSystem::write(
			$fileLocation . JsonDataFolderService::REQUESTS_FOLDER . self::STOCK_ASSET_PRICE_FILENAME,
			Json::encode($stockAssetsToDownload),
		);
	}

	public function generateDividendsJsonFile(string $fileLocation): void
	{
		$stockAssets = $this->stockAssetRepository->findByStockAssetDividendSource(
			StockAssetDividendSourceEnum::WEB,
		);

		$stockAssetsToDownload = [];
		foreach ($stockAssets as $stockAsset) {
			$stockAssetsToDownload[] = [
				'id' => $stockAsset->getId()->toString(),
				'name' => $stockAsset->getName(),
				'currency' => $stockAsset->getCurrency()->value,
				'url' => $this->jsonWebDataService->getStockAssetDividendsUrl($stockAsset),
			];
		}

		FileSystem::write(
			$fileLocation . JsonDataFolderService::REQUESTS_FOLDER . self::STOCK_ASSET_DIVIDENDS_FILENAME,
			Json::encode($stockAssetsToDownload),
		);
	}

}
