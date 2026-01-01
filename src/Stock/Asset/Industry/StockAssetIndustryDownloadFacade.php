<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Valuation\Data\StockValuationDataNumericHelper;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;

class StockAssetIndustryDownloadFacade
{

	public function __construct(
		private JsonDataFolderService $jsonDataFolderService,
		private DatetimeFactory $datetimeFactory,
		private StockAssetIndustryRepository $stockAssetIndustryRepository,
		private LoggerInterface $logger,
		private SystemValueFacade $systemValueFacade,
	)
	{
	}

	public function process(): void
	{
		$file = $this->jsonDataFolderService->getResultsFolder() . JsonDataSourceProviderFacade::STOCK_ASSET_INDUSTRY;

		if (file_exists($file) === false) {
			return;
		}

		/** @var array<array{html: string}> $parsedJson */
		$parsedJson = Json::decode(FileSystem::read($file), true);
		assert(is_array($parsedJson));

		if (count($parsedJson) !== 1) {
			return;
		}

		$this->logger->debug('Processing stock asset industries');

		$now = $this->datetimeFactory->createNow();

		$domDocument = new DOMDocument();
		@$domDocument->loadHTML($parsedJson[0]['html']);
		$domXpath = new DOMXPath($domDocument);

		$tableNodes = $domXpath->query('//div[2]/div/table[3]');
		if ($tableNodes === false || $tableNodes->length === 0) {
			return;
		}

		$table = $tableNodes->item(0);
		assert($table instanceof DOMNode);
		$rows = $domXpath->query('.//tbody/tr', $table);

		if ($rows === false || $rows->length === 0) {
			return;
		}

		$allIndustries = $this->stockAssetIndustryRepository->findAll();
		$indexedIndustries = [];
		foreach ($allIndustries as $existingAssetIndustry) {
			$indexedIndustries[$existingAssetIndustry->getMappingName()] = $existingAssetIndustry;
		}

		$updatedCount = 0;
		foreach ($rows as $row) {
			assert($row instanceof DOMNode);
			$cells = $domXpath->query('.//td', $row);

			if ($cells === false || $cells->length === 0) {
				continue;
			}

			$cellValues = [];
			foreach ($cells as $cell) {
				assert($cell instanceof DOMNode);
				$cellValues[] = Strings::trim($cell->textContent);
			}

			if (($cellValues[0] ?? null) === null) {
				continue;
			}

			$data = [
				'name' => $cellValues[1],
				'marketCap' => $cellValues[2] ?? 0,
				'pe' => $cellValues[3] ?? null,
				'fwdPe' => $cellValues[4] ?? null,
				'peg' => $cellValues[5] ?? null,
				'ps' => $cellValues[6] ?? null,
				'pb' => $cellValues[7] ?? null,
				'pc' => $cellValues[8] ?? null,
				'pfcf' => $cellValues[9] ?? null,
			];

			if (array_key_exists($data['name'], $indexedIndustries)) {
				$this->logger->debug('Updating industry', ['industry' => $data['name']]);
				$indexedIndustries[$data['name']]->updateValues(
					$now,
					$this->parseFloat($data['pe']),
					StockValuationDataNumericHelper::parseNumericValue((string) $data['marketCap']),
					$this->parseFloat($data['pfcf']),
					$this->parseFloat($data['pc']),
					$this->parseFloat($data['pb']),
					$this->parseFloat($data['ps']),
					$this->parseFloat($data['peg']),
					$this->parseFloat($data['fwdPe']),
				);
				unset($indexedIndustries[$data['name']]);
				$updatedCount++;
			}
		}

		$this->logger->debug(sprintf('Processed %d industries', $updatedCount));
		if (count($indexedIndustries) > 0) {
			$this->logger->error(sprintf(
				'Missing %d industries while updating values',
				count($indexedIndustries),
			));
		}

		$this->systemValueFacade->updateValue(
			SystemValueEnum::STOCK_VALUATION_UPDATED_STOCK_ASSET_INDUSTRIES_COUNT,
			intValue: $updatedCount,
		);
	}

	private function parseFloat(mixed $value): float|null
	{
		if ($value === null || $value === '-' || $value === '') {
			return null;
		}

		if (is_numeric($value) === false) {
			return null;
		}

		return (float) $value;
	}

}
