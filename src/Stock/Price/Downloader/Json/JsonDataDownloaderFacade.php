<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json;

use App\Asset\Price\AssetPriceDownloader;
use App\Asset\Price\AssetPriceRecord;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
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
	)
	{
	}

	/**
	 * @return array<AssetPriceRecord>
	 */
	public function getPriceForAssets(): array
	{
		$file = $this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME;

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

			$priceRecord = $this->stockAssetPriceRecordRepository->findByStockAssetAndDate(
				$stockAsset,
				$today,
			);

			if ($priceRecord !== null) {
				$priceRecord->updatePrice($priceValue, $now);
			} else {
				$priceRecord = new StockAssetPriceRecord(
					$today,
					$stockAsset->getCurrency(),
					$priceValue,
					$stockAsset,
					StockAssetPriceDownloaderEnum::WEB_SCRAP,
					$now,
				);

				$this->entityManager->persist($priceRecord);
			}

			$stockAsset->setCurrentPrice($priceRecord, $now);
			$priceRecords[] = $priceRecord;
		}

		$this->entityManager->flush();

		$processedFile = sprintf(
			'%s%s-%s',
			$this->jsonDataFolderService->getParsedResultsFolder(),
			$now->getTimestamp(),
			JsonDataSourceProviderFacade::STOCK_ASSET_PRICE_FILENAME,
		);

		FileSystem::copy($file, $processedFile);
		FileSystem::delete($file);

		return $priceRecords;
	}

	private function processPrice(string $price): float
	{
		return (float) preg_replace('/[^0-9.]/', '', $price);
	}

}
