<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json;

use App\Asset\Price\AssetPriceSourceProvider;
use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
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

	public const STOCK_ASSET_FINANCIALS_FILENAME = 'financials.json';

	public const STOCK_ASSET_KEY_STATISTICS_FILENAME = 'keyStatistics.json';

	public const STOCK_ASSET_ANALYST_INSIGHT = 'analystInsights.json';

	public const STOCK_ASSET_INDUSTRY = 'stockAssetIndustry.json';

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

	public function generateStockValuationJsonFile(
		string $fileLocation,
		StockAsset|null $selectedStockAsset = null,
	): void
	{
		if ($selectedStockAsset === null) {
			$stockAssets = $this->stockAssetRepository->getAllActiveValuationAssets();
		} else {
			$stockAssets = [$selectedStockAsset];
		}

		$keyStatistics = [];
		$financials = [];
		$analystInsights = [];
		$stockAssetIndustry = [
			[
				'id' => 'stockAssetIndustry',
				'name' => 'stockAssetIndustry',
				'currency' => CurrencyEnum::USD->value,
				'url' => $this->jsonWebDataService->getStockAssetIndustryUrl(),
			],
		];

		foreach ($stockAssets as $stockAsset) {
			$keyStatistics[] = [
				'id' => $stockAsset->getId()->toString(),
				'name' => $stockAsset->getName(),
				'currency' => $stockAsset->getCurrency()->value,
				'url' => $this->jsonWebDataService->getKeyStatisticsDataUrl($stockAsset),
			];

			$financials[] = [
				'id' => $stockAsset->getId()->toString(),
				'name' => $stockAsset->getName(),
				'currency' => $stockAsset->getCurrency()->value,
				'url' => $this->jsonWebDataService->getFinancialsDataUrl($stockAsset),
			];

			$analystInsights[] = [
				'id' => $stockAsset->getId()->toString(),
				'name' => $stockAsset->getName(),
				'currency' => $stockAsset->getCurrency()->value,
				'url' => $this->jsonWebDataService->getAnalystInsightUrl($stockAsset),
			];
		}

		FileSystem::write(
			$fileLocation . JsonDataFolderService::REQUESTS_FOLDER . self::STOCK_ASSET_KEY_STATISTICS_FILENAME,
			Json::encode($keyStatistics),
		);

		FileSystem::write(
			$fileLocation . JsonDataFolderService::REQUESTS_FOLDER . self::STOCK_ASSET_FINANCIALS_FILENAME,
			Json::encode($financials),
		);

		FileSystem::write(
			$fileLocation . JsonDataFolderService::REQUESTS_FOLDER . self::STOCK_ASSET_ANALYST_INSIGHT,
			Json::encode($analystInsights),
		);

		FileSystem::write(
			$fileLocation . JsonDataFolderService::REQUESTS_FOLDER . self::STOCK_ASSET_INDUSTRY,
			Json::encode($stockAssetIndustry),
		);
	}

}
