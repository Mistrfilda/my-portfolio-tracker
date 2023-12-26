<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Web;

use App\Asset\Price\AssetPriceDownloader;
use App\Asset\Price\AssetPriceRecord;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;

class WebDataDownloaderFacade implements AssetPriceDownloader
{

	public function __construct(
		private string $url,
		private readonly bool $verifySsl,
		private readonly int $updateStockAssetHoursThreshold,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly Psr18ClientFactory $psr18ClientFactory,
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
		$stockAssets = $this->stockAssetRepository->findAllByAssetPriceDownloader(
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			priceDownloadedBefore: $this->datetimeFactory->createNow()->deductHoursFromDatetime(
				$this->updateStockAssetHoursThreshold,
			),
		);

		if (count($stockAssets) === 0) {
			return [];
		}

		$client = $this->psr18ClientFactory->getClient(['verify' => $this->verifySsl]);

		$priceRecords = [];
		foreach ($stockAssets as $stockAsset) {
			$this->logger->debug(
				sprintf('Processing price for stock asset %s', $stockAsset->getName()),
			);

			$response = $client->sendRequest(
				$this->psr7RequestFactory->createGETRequest(
					sprintf(
						$this->url,
						$stockAsset->getTicker(),
					),
				),
			);

			$contents = $response->getBody()->getContents();

			$domDocument = new DOMDocument();
			@$domDocument->loadHTML($contents);

			$domXpath = new DOMXPath($domDocument);
			$nodes = $domXpath->query("//div[contains(@id, 'quote-header-info')]");

			$today = $this->datetimeFactory->createToday();
			$now = $this->datetimeFactory->createNow();

			assert($nodes instanceof DOMNodeList);
			foreach ($nodes as $node) {
				$priceTags = $domXpath->query(".//fin-streamer[@data-field='regularMarketPrice']", $node);
				assert($priceTags instanceof DOMNodeList);
				assert($priceTags->count() === 1);
				assert($priceTags[0] instanceof DOMElement);
				$nodePriceValue = (float) preg_replace('/[^0-9.]/', '', (string) $priceTags[0]->nodeValue);

				$nodePriceValue = $stockAsset->getCurrency()->processFromWeb($nodePriceValue);

				$priceRecord = $this->stockAssetPriceRecordRepository->findByStockAssetAndDate(
					$stockAsset,
					$today,
				);

				if ($priceRecord !== null) {
					$priceRecord->updatePrice($nodePriceValue, $now);
				} else {
					$priceRecord = new StockAssetPriceRecord(
						$today,
						$stockAsset->getCurrency(),
						$nodePriceValue,
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
			sleep(5);
		}

		return $priceRecords;
	}

}
