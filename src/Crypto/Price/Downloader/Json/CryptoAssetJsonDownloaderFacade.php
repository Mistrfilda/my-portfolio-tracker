<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\Json;

use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;

class CryptoAssetJsonDownloaderFacade
{

	public function __construct(
		private CryptoJsonDataSourceProviderFacade $cryptoJsonDataSourceProviderFacade,
		private CryptoAssetPriceRecordRepository $cryptoAssetPriceRecordRepository,
		private CryptoAssetRepository $cryptoAssetRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private LoggerInterface $logger,
	)
	{
	}

	/**
	 * @return array<CryptoAssetPriceRecord>
	 */
	public function processResults(): array
	{
		$results = [];

		$file = $this->cryptoJsonDataSourceProviderFacade->getCryptoTablePath();

		if (file_exists($file) === false) {
			$this->logger->warning('Crypto JSON file not found', ['file' => $file]);
			return [];
		}

		$parsedJson = Json::decode(FileSystem::read($file), forceArrays: true);
		assert(is_array($parsedJson));

		$today = $this->datetimeFactory->createToday();
		$now = $this->datetimeFactory->createNow();

		// Projít všechny položky v JSON
		foreach ($parsedJson as $item) {
			if (!is_array($item) || !isset($item['html'])) {
				continue;
			}

			preg_match_all(
				'/<tr[^>]*data-testid-row="[^"]*"[^>]*>(.*?)<\/tr>/s',
				TypeValidator::validateString($item['html']),
				$rowMatches,
			);

			foreach ($rowMatches[1] as $rowHtml) {
				if (preg_match('/href="\/quote\/([^\/]+)\/"/i', $rowHtml, $tickerMatch) === false) {
					continue;
				}

				$fullTicker = $tickerMatch[1];
				if (preg_match(
					'/data-symbol="' . preg_quote(
						$fullTicker,
						'/',
					) . '"[^>]*data-field="regularMarketPrice"[^>]*data-value="([^"]+)"/s',
					$rowHtml,
					$priceMatch,
				) === false) {
					continue;
				}

				$price = (float) $priceMatch[1];

				$ticker = Strings::before($fullTicker, '-');
				if ($ticker === null) {
					$ticker = $fullTicker; // fallback pro případy bez -
				}

				$cryptoAsset = $this->cryptoAssetRepository->findByTicker($ticker);
				if ($cryptoAsset === null) {
					continue;
				}

				$existingRecord = $this->cryptoAssetPriceRecordRepository->findByCryptoAssetAndDate(
					$cryptoAsset,
					$today,
				);

				if ($existingRecord !== null) {
					$existingRecord->updatePrice($price, $now);
					$results[] = $existingRecord;
					$this->logger->info('Updated crypto price', [
						'ticker' => $ticker,
						'price' => $price,
					]);
					$priceRecord = $existingRecord;
				} else {
					$priceRecord = new CryptoAssetPriceRecord(
						$today,
						$cryptoAsset->getCurrency(),
						$price,
						$cryptoAsset,
						$now,
					);
					$this->entityManager->persist($priceRecord);
					$results[] = $priceRecord;
					$this->logger->info('Created crypto price record', [
						'ticker' => $ticker,
						'price' => $price,
					]);
				}

				$cryptoAsset->setCurrentPrice($priceRecord, $now);
			}
		}

		$this->entityManager->flush();

		return $results;
	}

}
