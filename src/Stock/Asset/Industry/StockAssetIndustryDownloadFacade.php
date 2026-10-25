<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry;

use App\Asset\Price\Downloader\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use App\Stock\Valuation\Data\StockValuationDataNumericHelper;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Psr\Log\LoggerInterface;
use const LIBXML_NONET;

class StockAssetIndustryDownloadFacade
{

	private const array VALUE_COLUMNS = ['Name', 'Market Cap', 'P/E', 'Fwd P/E', 'PEG', 'P/S', 'P/B', 'P/C', 'P/FCF'];

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

		$this->processHtml($parsedJson[0]['html']);
	}

	public function processHtml(string $html): int
	{
		$industries = $this->parseHtml($html);
		$this->logger->debug('Processing stock asset industries');

		$allIndustries = $this->stockAssetIndustryRepository->findAll();
		$indexedIndustries = [];
		foreach ($allIndustries as $existingAssetIndustry) {
			$indexedIndustries[$existingAssetIndustry->getMappingName()] = $existingAssetIndustry;
		}

		$matchingIndustries = array_intersect_key($industries, $indexedIndustries);
		if ($matchingIndustries === []) {
			throw new StockAssetIndustryImportException('No imported industry names match the configured mappings.');
		}

		$now = $this->datetimeFactory->createNow();
		$updatedCount = 0;
		foreach ($matchingIndustries as $name => $data) {
			$this->logger->debug('Updating industry', ['industry' => $name]);
			$indexedIndustries[$name]->updateValues(
				$now,
				$this->parseFloat($data['P/E']),
				StockValuationDataNumericHelper::parseNumericValue($data['Market Cap']),
				$this->parseFloat($data['P/FCF']),
				$this->parseFloat($data['P/C']),
				$this->parseFloat($data['P/B']),
				$this->parseFloat($data['P/S']),
				$this->parseFloat($data['PEG']),
				$this->parseFloat($data['Fwd P/E']),
			);
			unset($indexedIndustries[$name]);
			$updatedCount++;
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

		return $updatedCount;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function parseHtml(string $html): array
	{
		$domDocument = new DOMDocument();
		if (Strings::trim($html) === '' || @$domDocument->loadHTML($html, LIBXML_NONET) === false) {
			throw new StockAssetIndustryImportException('The imported HTML is empty or invalid.');
		}

		$domXpath = new DOMXPath($domDocument);
		$headerNodes = $domXpath->query('//tr[./*[self::td or self::th][normalize-space(.) = "P/FCF"]]');
		if ($headerNodes === false) {
			throw new StockAssetIndustryImportException('The industry valuation table could not be found.');
		}

		foreach ($headerNodes as $header) {
			if ($header instanceof DOMElement === false) {
				continue;
			}

			$columns = array_flip($this->getCellValues($header));
			if (array_diff(['No.', ...self::VALUE_COLUMNS], array_keys($columns)) !== []) {
				continue;
			}

			$table = $header->parentNode;
			while ($table !== null && !($table instanceof DOMElement && $table->tagName === 'table')) {
				$table = $table->parentNode;
			}

			if ($table === null) {
				continue;
			}

			$rows = $domXpath->query('.//tr', $table);
			assert($rows !== false);
			$industries = [];
			foreach ($rows as $row) {
				if ($row instanceof DOMElement === false) {
					continue;
				}

				$cellValues = $this->getCellValues($row);
				if (Strings::match($cellValues[$columns['No.']] ?? '', '~^[0-9]+$~D') === null) {
					continue;
				}

				$data = [];
				foreach (self::VALUE_COLUMNS as $column) {
					if (array_key_exists($columns[$column], $cellValues) === false) {
						throw new StockAssetIndustryImportException('An imported valuation row is incomplete.');
					}

					$data[$column] = $cellValues[$columns[$column]];
				}

				if ($data['Name'] === '' || array_key_exists($data['Name'], $industries)) {
					throw new StockAssetIndustryImportException('An imported industry name is empty or duplicated.');
				}

				$industries[$data['Name']] = $data;
			}

			if ($industries !== []) {
				return $industries;
			}
		}

		throw new StockAssetIndustryImportException(
			'The HTML does not contain an industry valuation table with data.',
		);
	}

	/**
	 * @return list<string>
	 */
	private function getCellValues(DOMElement $row): array
	{
		$values = [];
		foreach ($row->childNodes as $cell) {
			if ($cell instanceof DOMElement && in_array($cell->tagName, ['td', 'th'], true)) {
				$values[] = Strings::trim(Strings::replace($cell->textContent, '~\s+~u', ' '));
			}
		}

		return $values;
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
