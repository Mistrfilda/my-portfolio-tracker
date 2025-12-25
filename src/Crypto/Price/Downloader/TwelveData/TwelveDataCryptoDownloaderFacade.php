<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\TwelveData;

use App\Asset\Price\AssetPriceDownloader;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Currency\CurrencyEnum;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;

class TwelveDataCryptoDownloaderFacade implements AssetPriceDownloader
{

	public function __construct(
		private readonly string $apiKey,
		private readonly CryptoAssetRepository $cryptoAssetRepository,
		private readonly CryptoAssetPriceRecordRepository $cryptoAssetPriceRecordRepository,
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
	 * @return array<CryptoAssetPriceRecord>
	 */
	public function getPriceForAssets(): array
	{
		$today = $this->datetimeFactory->createToday();
		$now = $this->datetimeFactory->createNow();
		$priceRecords = [];

		foreach ($this->cryptoAssetRepository->findAll() as $cryptoAsset) {
			$this->logger->debug(sprintf('Downloading crypto price for %s', $cryptoAsset->getName()));
			$response = $this->psr18ClientFactory->getClient()->sendRequest(
				$this->psr7RequestFactory->createGETRequest(
					$this->getRequest($cryptoAsset)->getFormattedRequestUrl(),
				),
			);

			$parsedContents = Json::decode($response->getBody()->getContents(), true);
			assert(is_array($parsedContents));

			if (array_key_exists('rate', $parsedContents) === true && is_numeric($parsedContents['rate'])) {
				$price = (float) $parsedContents['rate'];
			} else {
				continue;
			}

			$priceRecord = $this->cryptoAssetPriceRecordRepository->findByCryptoAssetAndDate(
				$cryptoAsset,
				$today,
			);

			if ($priceRecord !== null) {
				$priceRecord->updatePrice($price, $now);
			} else {
				$priceRecord = new CryptoAssetPriceRecord(
					$today,
					$cryptoAsset->getCurrency(),
					$price,
					$cryptoAsset,
					$now,
				);

				$this->entityManager->persist($priceRecord);
			}

			$cryptoAsset->setCurrentPrice($priceRecord, $now);

			$priceRecords[] = $priceRecord;

			$this->entityManager->flush();
		}

		$this->systemValueFacade->updateValue(
			SystemValueEnum::CRYPTO_CURRENCY_DOWNLOADED_COUNT,
			intValue: count($priceRecords),
		);

		return $priceRecords;
	}

	private function getRequest(CryptoAsset $cryptoAsset): TwelveDataCryptoRequest
	{
		return new TwelveDataCryptoRequest(
			$this->apiKey,
			$cryptoAsset,
			CurrencyEnum::USD,
		);
	}

}
