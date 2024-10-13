<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Downloader\Json;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Downloader\StockAssetDividendDownloader;
use App\Stock\Dividend\Downloader\StockAssetDividendDownloaderDTO;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Price\Downloader\Json\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use stdClass;
use const PREG_SET_ORDER;

class StockAssetJsonDividendDownloader implements StockAssetDividendDownloader
{

	private const DIVIDEND_PATTERN = '/Dividend\s+([A-Za-z]+)\s+(\d{1,2}),\s+(\d{4})\s+(\d+\.\d{2})/';

	public function __construct(
		private JsonDataFolderService $jsonDataFolderService,
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private LoggerInterface $logger,
	)
	{

	}

	public function downloadDividendRecords(): void
	{
		$file = $this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME;

		if (file_exists($file) === false) {
			return;
		}

		$parsedJson = Json::decode(FileSystem::read($file));
		assert(is_array($parsedJson));

		$now = $this->datetimeFactory->createNow();

		/** @var object{id: string, currency: string, textContent: string, html: string}&stdClass $parsedStockAsset */
		foreach ($parsedJson as $parsedStockAsset) {
			$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($parsedStockAsset->id));

			preg_match_all(
				self::DIVIDEND_PATTERN,
				$parsedStockAsset->textContent,
				$matches,
				PREG_SET_ORDER,
			);

			$values = [];

			foreach ($matches as $match) {
				$date = DatetimeFactory::createFromFormat(
					sprintf('%s %s %s', $match[1], $match[2], $match[3]),
					'M d Y',
				)->setTime(0, 0);

				$values[] = new StockAssetDividendDownloaderDTO(
					$date,
					null,
					$date,
					$stockAsset->getCurrency(),
					$this->processPrice($match[4]),
				);
			}

			foreach ($values as $value) {
				if ($this->stockAssetDividendRepository->findOneByStockAssetExDate(
					$stockAsset,
					$value->getExDate(),
				) !== null) {
					continue;
				}

				$this->logger->info(sprintf('new dividend for stock asset %s', $stockAsset->getName()));

				$this->entityManager->persist(
					new StockAssetDividend(
						$stockAsset,
						$value->getExDate(),
						$value->getPaymentDate(),
						$value->getDeclarationDate(),
						$stockAsset->getCurrency(),
						$value->getAmount(),
						$now,
					),
				);
			}

			$this->entityManager->flush();
		}

		$processedFile = sprintf(
			'%s%s-%s',
			$this->jsonDataFolderService->getParsedResultsFolder(),
			$now->getTimestamp(),
			JsonDataSourceProviderFacade::STOCK_ASSET_DIVIDENDS_FILENAME,
		);

		FileSystem::copy($file, $processedFile);
		FileSystem::delete($file);
	}

	private function processPrice(string $price): float
	{
		return (float) preg_replace('/[^0-9.]/', '', $price);
	}

}
