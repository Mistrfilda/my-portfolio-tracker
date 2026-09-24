<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Pse;

use App\Asset\Price\AssetPriceDownloader;
use App\Asset\Price\AssetPriceRecord;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Stock\Asset\Download\StockAssetDataImportGuard;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\Pse\Exception\PseInvalidResponseException;
use App\Stock\Price\Downloader\Pse\Exception\PseMissingStockAssetIsinException;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PseDataDownloaderFacade implements AssetPriceDownloader
{

	/**
	 * @param array<array{url: string, pricePositionTag: int, tableTdsCount: int}> $requests
	 */
	public function __construct(
		private readonly bool $verifySsl,
		private readonly int $updateStockAssetHoursThreshold,
		private readonly array $requests,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly EntityManagerInterface $entityManager,
		private readonly LoggerInterface $logger,
		private readonly SystemValueFacade $systemValueFacade,
		private StockAssetDataImportGuard $importGuard,
	)
	{
	}

	/**
	 * @return array<AssetPriceRecord>
	 */
	public function getPriceForAssets(StockAsset|null $selectedStockAsset = null): array
	{
		$stockAssets = $selectedStockAsset !== null ? [$selectedStockAsset] : $this->stockAssetRepository->findAllByAssetPriceDownloader(
			StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER,
			priceDownloadedBefore: $this->datetimeFactory->createNow()->deductHoursFromDatetime(
				$this->updateStockAssetHoursThreshold,
			),
		);

		if (count($stockAssets) === 0) {
			return [];
		}

		$client = $this->psr18ClientFactory->getClient(['verify' => $this->verifySsl]);

		$now = $this->datetimeFactory->createNow();
		$parsedIsinsWithPrice = [];
		foreach ($this->getRequests() as $request) {
			$response = $client->sendRequest(
				$this->psr7RequestFactory->createGETRequest($request->getUrl()),
			);

			$parsedResponse = Strings::match(
				$response->getBody()->getContents(),
				'/<body[^>]*>(.*?)<\/body>/is',
			);

			if ($parsedResponse === null) {
				throw new PseInvalidResponseException();
			}

			$htmlBody = Arrays::first($parsedResponse);
			if ($htmlBody === null) {
				throw new PseInvalidResponseException();
			}

			$domDocument = new DOMDocument();
			@$domDocument->loadHTML($htmlBody);

			$domXpath = new DOMXPath($domDocument);
			$trNodes = $domXpath->query("//div[contains(@class, 'stock-table')]/table/tbody/tr");

			assert($trNodes instanceof DOMNodeList);
			foreach ($trNodes as $node) {
				if ($node instanceof DOMElement) {
					$isinNode = $domXpath->query(".//div[contains(@class, 'isin')]", $node);

					assert($isinNode instanceof DOMNodeList);
					assert($isinNode->count() === 1);

					$isinValue = $isinNode->item(0)?->nodeValue;
					assert(is_string($isinValue));
					$isin = Strings::trim($isinValue);

					$tdNodes = $domXpath->query('.//td', $node);

					assert($tdNodes instanceof DOMNodeList);
					assert($tdNodes->count() === $request->getTableTdsCount());

					//@phpstan-ignore-next-line
					$price = $tdNodes->item($request->getPricePositionTag())->attributes->item(0)->nodeValue;

					$parsedIsinsWithPrice[$isin] = $price;
				}
			}

			//wait before second request
			sleep(2);
		}

		$priceRecords = [];
		$today = $this->datetimeFactory->createToday();
		foreach ($stockAssets as $stockAsset) {
			if ($stockAsset->getIsin() === null) {
				throw new PseMissingStockAssetIsinException();
			}

			if (array_key_exists($stockAsset->getIsin(), $parsedIsinsWithPrice)) {
				$price = (float) $parsedIsinsWithPrice[$stockAsset->getIsin()];

				if (!is_finite($price) || $price <= 0) {
					throw new RuntimeException('Downloaded stock price must be positive.');
				}

				$this->importGuard->import(
					$stockAsset,
					StockAssetDataType::PRICE,
					$now,
					function () use ($stockAsset, $price, $today, $now, &$priceRecords): void {
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
								StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER,
								$now,
							);

							$this->entityManager->persist($priceRecord);
						}

						$stockAsset->setCurrentPrice($priceRecord, $now);

						$priceRecords[] = $priceRecord;
					},
				);
			} else {
				if ($selectedStockAsset !== null) {
					throw new RuntimeException('PSE did not return the requested stock price.');
				}

				$this->logger->error(
					sprintf(
						'Missing price for stock asset ID %s - %s',
						$stockAsset->getId(),
						$stockAsset->getName(),
					),
				);
			}
		}

		$this->entityManager->flush();

		if ($selectedStockAsset === null) {
			$this->systemValueFacade->updateValue(SystemValueEnum::PSE_DATA_UPDATED_AT, datetimeValue: $now);
		}

		return $priceRecords;
	}

	/**
	 * @return array<int, PseDataRequest>
	 */
	private function getRequests(): array
	{
		$requests = [];
		foreach ($this->requests as $request) {
			$requests[] = new PseDataRequest($request['url'], $request['pricePositionTag'], $request['tableTdsCount']);
		}

		return $requests;
	}

}
