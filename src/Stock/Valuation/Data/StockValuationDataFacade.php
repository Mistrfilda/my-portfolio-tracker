<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use stdClass;

class StockValuationDataFacade
{

	public function __construct(
		private JsonDataFolderService $jsonDataFolderService,
		private StockAssetRepository $stockAssetRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private StockValuationDataRepository $stockValuationDataRepository,
		private SystemValueFacade $systemValueFacade,
		private LoggerInterface $logger,
	)
	{
	}

	public function processResults(): void
	{
		$file = $this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_KEY_STATISTICS_FILENAME;

		if (file_exists($file) === false) {
			return;
		}

		$parsedJson = Json::decode(FileSystem::read($file));
		assert(is_array($parsedJson));

		$now = $this->datetimeFactory->createNow();

		/** @var object{id: string, currency: string, textContent: string, html: string}&stdClass $parsedStockAsset */
		foreach ($parsedJson as $parsedStockAsset) {
			$parser = new StockValuationDataParser($parsedStockAsset->html);
			$data = $parser->parseStockData();

			$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($parsedStockAsset->id));
			$this->logger->debug(sprintf('Processing stock asset %s', $stockAsset->getName()));

			$this->stockValuationDataRepository->removeTodayData($stockAsset, $now);
			$this->stockValuationDataRepository->updateLastActive($stockAsset);

			foreach ($data as $key => $stockDataGroup) {
				if ($key === StockValuationTypeGroupEnum::BASIC_INFO->value) {
					continue;
				}

				foreach ($stockDataGroup as $valueKey => $value) {
					$valueType = StockValuationTypeEnum::from($valueKey);

					$floatValue = null;
					$stringValue = $value;

					if ($value === '--') {
						$stringValue = null;
					} else {
						if ($valueType->getTypeValueType() === StockValuationTypeValueTypeEnum::PERCENTAGE) {
							$floatValue = (float) str_replace('%', '', $value ?? '');
						} elseif ($valueType->getTypeValueType() === StockValuationTypeValueTypeEnum::FLOAT) {
							$floatValue = $parser->parseNumericValue($value);
						}
					}

					$stockValuationData = new StockValuationData(
						$stockAsset,
						$valueType,
						$valueType->getTypeGroup(),
						$valueType->getTypeValueType(),
						$now,
						$stringValue,
						$floatValue,
						$stockAsset->getCurrency(),
						$now,
					);

					$this->entityManager->persist($stockValuationData);
					$this->entityManager->flush();
				}
			}

			$this->logger->debug(sprintf('Processed stock asset %s', $stockAsset->getName()));
		}

		$processedFile = sprintf(
			'%s%s-%s',
			$this->jsonDataFolderService->getParsedResultsFolder(),
			$now->getTimestamp(),
			JsonDataSourceProviderFacade::STOCK_ASSET_KEY_STATISTICS_FILENAME,
		);

		$this->systemValueFacade->updateValue(
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_COUNT,
			intValue: count($parsedJson),
		);

		$this->systemValueFacade->updateValue(
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_AT,
			datetimeValue: $now,
		);

		FileSystem::copy($file, $processedFile);
		FileSystem::delete($file);
	}

}
