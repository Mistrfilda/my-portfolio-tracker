<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Expense\Bank\BankExpenseParser;
use Nette\Utils\Strings;
use Smalot\PdfParser\Parser;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

class KbPdfCashParser implements BankExpenseParser
{

	public function __construct(private KbContentParser $kbContentParser)
	{

	}

	public function parse(string $fileContents): KbContentParserResult
	{
		$parser = new Parser();
		$pdf = $parser->parseContent($fileContents);

		$tablesData = [];
		$break = false;
		foreach ($pdf->getPages() as $page) {
			if (str_contains($page->getText(), 'Pokračování na další straně')) {
				$pattern = '/Připsáno\nOdepsáno(.*?)Pokračování na další straně/s';
			} else {
				$pattern = '/Připsáno\nOdepsáno(.*?)KONEČNÝ ZŮSTATEK/s';
				$break = true;
			}

			preg_match($pattern, $page->getText(), $matches);
			$tablesData[] = $matches[1];

			if ($break) {
				break;
			}
		}

		$transactions = [];
		foreach ($tablesData as $singlePageData) {
			$transactions = array_merge($transactions, $this->getTransactionsFromPageContent($singlePageData));
		}

		return $this->kbContentParser->processRawKbTransactions($transactions);
	}

	/**
	 * @return array<KbTransaction>
	 */
	private function getTransactionsFromPageContent(string $content): array
	{
		$transactions = [];
		$pattern = '/(\d{2}\.\d{2}\.\d{4})/';
		$parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($parts === false) {
			throw new KbPdfTransactionParsingErrorException('Invalid file');
		}

		$filteredParts = array_filter($parts, static fn (string $part) => Strings::trim($part) !== '');

		$currentTransaction = null;
		foreach ($filteredParts as $filteredPart) {
			if ((bool) preg_match($pattern, $filteredPart)) {
				if ($currentTransaction === null) {
					$currentTransaction = new KbTransaction();
					$currentTransaction->setSettlementDate($filteredPart);
				} else {
					$currentTransaction->setTransactionDate($filteredPart);
				}
			} else {
				if ($currentTransaction === null) {
					throw new KbPdfTransactionParsingErrorException();
				}

				$currentTransaction->setTransactionRawContent(Strings::trim($filteredPart));
				$transactions[] = $currentTransaction;
				$currentTransaction = null;
			}
		}

		return $transactions;
	}

}
