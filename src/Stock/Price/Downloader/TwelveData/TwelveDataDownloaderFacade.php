<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\TwelveData;

use App\Asset\Price\AssetPriceDownloader;
use App\Asset\Price\AssetPriceRecord;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\TwelveData\Exception\TwelveDataInvalidValueException;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;

class TwelveDataDownloaderFacade implements AssetPriceDownloader
{

	public function __construct(
		private readonly string $apiKey,
		private readonly int $updateStockAssetHoursThreshold,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly EntityManagerInterface $entityManager,
		private readonly LoggerInterface $logger,
		private readonly SystemValueFacade $systemValueFacade,
	)
	{
	}

	/**
	 * @return array<AssetPriceRecord>
	 */
	public function getPriceForAssets(): array
	{
		$twelveDataRequest = $this->getRequest();

		if ($twelveDataRequest->count() === 0) {
			return [];
		}

		$response = $this->psr18ClientFactory->getClient()->sendRequest(
			$this->psr7RequestFactory->createGETRequest($twelveDataRequest->getFormattedRequestUrl()),
		);

		$parsedContents = Json::decode($response->getBody()->getContents(), true);

		$today = $this->datetimeFactory->createToday();
		$now = $this->datetimeFactory->createNow();
		$priceRecords = [];

		assert(is_array($parsedContents));

		foreach ($parsedContents as $ticker => $priceBody) {
			$stockAsset = $twelveDataRequest->getStockAssetForTicker($ticker);
			if ($stockAsset === null) {
				$this->logger->error(
					sprintf('Missing price for ticker %s in %s', $ticker, self::class),
				);

				continue;
			}

			if (is_string($priceBody)) {
				$price = (float) $priceBody;
			} elseif (
				is_array($priceBody)
				&& array_key_exists('price', $priceBody)
				&& is_numeric($priceBody['price'])
			) {
				$price = (float) $priceBody['price'];
			} else {
				throw new TwelveDataInvalidValueException();
			}

			$priceRecord = $this->stockAssetPriceRecordRepository->findByStockAssetAndDate(
				$stockAsset,
				$today,
			);

			if ($priceRecord !== null) {
				$priceRecord->updatePrice($price, $now);
			} else {
				$priceRecord = new StockAssetPriceRecord(
					$today,
					$stockAsset->getCurrency(),
					$price,
					$stockAsset,
					StockAssetPriceDownloaderEnum::TWELVE_DATA,
					$now,
				);

				$this->entityManager->persist($priceRecord);
			}

			$stockAsset->setCurrentPrice($priceRecord, $now);

			$priceRecords[] = $priceRecord;
		}

		$this->entityManager->flush();

		$this->systemValueFacade->updateValue(SystemValueEnum::TWELVE_DATA_UPDATED_AT, datetimeValue: $now);

		return $priceRecords;
	}

	private function getRequest(): TwelveDataRequest
	{
		$twelveDataRequest = new TwelveDataRequest($this->apiKey);

		foreach ($this->stockAssetRepository->findAllByAssetPriceDownloader(
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			8,
			$this->datetimeFactory->createNow()->deductHoursFromDatetime(
				$this->updateStockAssetHoursThreshold,
			),
		) as $stockAsset) {
			$twelveDataRequest->addStockAsset($stockAsset);
		}

		return $twelveDataRequest;
	}

}
