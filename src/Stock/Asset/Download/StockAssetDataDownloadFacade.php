<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\Json\StockAssetJsonDividendDownloader;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\Downloader\Json\JsonDataDownloaderFacade;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Price\Downloader\Pse\PseDataDownloaderFacade;
use App\Stock\Price\Downloader\TwelveData\TwelveDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Valuation\Data\StockValuationDataFacade;
use Nette\Utils\FileSystem;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Throwable;

class StockAssetDataDownloadFacade
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private JsonDataFolderService $folders,
		private JsonDataSourceProviderFacade $sources,
		private StockAssetScraperRunner $scraper,
		private JsonDataDownloaderFacade $prices,
		private StockAssetJsonDividendDownloader $dividends,
		private StockValuationDataFacade $valuations,
		private TwelveDataDownloaderFacade $twelveData,
		private PseDataDownloaderFacade $pse,
		private LoggerInterface $logger,
	)
	{
	}

	public function download(UuidInterface $id): void
	{
		$asset = $this->stockAssetRepository->getById($id);
		$folder = $this->folders->getFolder() . '/jobs/' . Uuid::uuid4()->toString();
		$files = new StockAssetDownloadFiles(new JsonDataFolderService($folder), $id);
		/** @var array<string, callable(): void> $steps */
		$steps = [];
		if ($asset->shouldBeUpdated()) {
			$steps['price'] = function () use ($asset, $files, $folder): void {
				match ($asset->getAssetPriceDownloader()) {
					StockAssetPriceDownloaderEnum::TWELVE_DATA => $this->twelveData->getPriceForAssets($asset),
					StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER => $this->pse->getPriceForAssets($asset),
					StockAssetPriceDownloaderEnum::WEB_SCRAP => $this->downloadWebPrice($asset, $files, $folder),
				};
			};
		}

		if ($asset->shouldBeUpdated() && $asset->getStockAssetDividendSource() === StockAssetDividendSourceEnum::WEB) {
			$steps['dividends'] = function () use ($asset, $files, $folder): void {
				$this->sources->generateDividendsJsonFile($folder, $asset);
				$this->scraper->run('dividends.js', $folder);
				$this->dividends->downloadDividendRecords($files);
			};
		}

		if ($asset->shouldDownloadValuation()) {
			$steps['valuation'] = function () use ($asset, $files, $folder): void {
				$this->sources->generateStockValuationJsonFile($folder, $asset);
				$this->scraper->run('financials.js', $folder);
				$this->valuations->processKeyStatistics($files);
			};
			$steps['analystInsights'] = function () use ($asset, $files, $folder): void {
				$this->sources->generateStockValuationJsonFile($folder, $asset);
				$this->scraper->run('analyst.js', $folder);
				$this->valuations->processAnalystInsights($files);
			};
		}

		$failed = [];
		try {
			foreach ([
				JsonDataFolderService::REQUESTS_FOLDER,
				JsonDataFolderService::RESULTS_FOLDER,
				JsonDataFolderService::PARSED_RESULTS_FOLDER,
			] as $subfolder) {
				FileSystem::createDir($folder . $subfolder);
			}

			foreach ($steps as $name => $step) {
				try {
					$step();
				} catch (Throwable $exception) {
					$failed[] = $name;
					$this->logger->error(
						'Stock data download failed.',
						['assetId' => $id->toString(), 'step' => $name, 'exception' => $exception],
					);
				}
			}
		} finally {
			FileSystem::delete($folder);
		}

		if ($failed !== []) {
			throw new RuntimeException(
				sprintf('Stock %s download failed: %s.', $id->toString(), implode(', ', $failed)),
			);
		}
	}

	private function downloadWebPrice(
		StockAsset $asset,
		StockAssetDownloadFiles $files,
		string $folder,
	): void
	{
		$this->sources->generatePriceSourcesJsonFile($folder, $asset);
		$this->scraper->run('prices.js', $folder);
		$this->prices->getPriceForAssets($files);
	}

}
