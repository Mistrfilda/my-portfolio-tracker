<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json;

use App\Asset\Price\AssetPriceDownloader;
use App\Asset\Price\AssetPriceRecord;
use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\Download\StockAssetDataImportGuard;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Asset\Download\StockAssetDownloadFiles;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use stdClass;

class JsonDataDownloaderFacade implements AssetPriceDownloader
{

	public function __construct(
		private JsonDataFolderService $jsonDataFolderService,
		private StockAssetRepository $stockAssetRepository,
		private DatetimeFactory $datetimeFactory,
		private StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private EntityManagerInterface $entityManager,
		private LoggerInterface $logger,
		private SystemValueFacade $systemValueFacade,
		private StockAssetDataImportGuard $importGuard,
	)
	{
	}

	/**
	 * @return array<AssetPriceRecord>
	 */
	public function getPriceForAssets(StockAssetDownloadFiles|null $download = null): array
	{
		$folders = $download->folders ?? $this->jsonDataFolderService;
		$file = $folders->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME;
		$download?->validate(JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME);

		if (file_exists($file) === false) {
			return [];
		}

		$parsedJson = Json::decode(FileSystem::read($file));
		assert(is_array($parsedJson));

		$priceRecords = [];
		$today = $this->datetimeFactory->createToday();
		$now = $this->datetimeFactory->createNow();

		/** @var object{id: string, currency: string, price: string}&stdClass $parsedStockAsset */
		foreach ($parsedJson as $parsedStockAsset) {
			$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($parsedStockAsset->id));
			$priceValue = $stockAsset->getCurrency()->processFromWeb($this->processPrice($parsedStockAsset->price));

			$this->logger->info(
				sprintf('Processing price for stock asset %s', $stockAsset->getName()),
			);

			if (!is_finite($priceValue) || $priceValue <= 0) {
				throw new RuntimeException('Downloaded stock price must be positive.');
			}

			$downloadedAt = StockAssetDownloadFiles::downloadedAt($parsedStockAsset, $now);
			$this->importGuard->import(
				$stockAsset,
				StockAssetDataType::PRICE,
				$downloadedAt,
				function () use ($stockAsset, $priceValue, $today, $downloadedAt, &$priceRecords): void {
					$priceRecord = $this->stockAssetPriceRecordRepository->findByStockAssetAndDate(
						$stockAsset,
						$today,
					);

					if ($priceRecord !== null) {
						$priceRecord->updatePrice($priceValue, $downloadedAt);
					} else {
						$priceRecord = new StockAssetPriceRecord(
							$today,
							$stockAsset->getCurrency(),
							$priceValue,
							$stockAsset,
							StockAssetPriceDownloaderEnum::WEB_SCRAP,
							$downloadedAt,
						);

						$this->entityManager->persist($priceRecord);
					}

					$stockAsset->setCurrentPrice($priceRecord, $downloadedAt);
					$priceRecords[] = $priceRecord;
				},
			);
		}

		$this->entityManager->flush();

		$processedFile = sprintf(
			'%s%s-%s',
			$folders->getParsedResultsFolder(),
			$now->getTimestamp(),
			JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME,
		);

		FileSystem::copy($file, $processedFile);
		FileSystem::delete($file);

		if ($download === null) {
			$this->systemValueFacade->updateValue(SystemValueEnum::PUPPETER_UPDATED_AT, datetimeValue: $now);
		}

		return $priceRecords;
	}

	private function processPrice(string $price): float
	{
		return (float) preg_replace('/[^0-9.]/', '', $price);
	}

}
