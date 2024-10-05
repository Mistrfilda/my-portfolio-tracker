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
		$pagesCount = count($pdf->getPages());
		foreach ($pdf->getPages() as $pageIndex => $page) {
			if ($pageIndex + 1 === $pagesCount) {
				break;
			}

			$pattern = '/Výpis z účtu(.*)/s';
			preg_match($pattern, $page->getText(), $matches);
			if (array_key_exists(1, $matches) === false) {
				throw new KbPdfTransactionParsingErrorException('Invalid file');
			}

			$text = $matches[1];

			if ($pageIndex === 0) {
				preg_match('/Transakce(.*)/s', $text, $matches);
				if (array_key_exists(1, $matches) === false) {
					throw new KbPdfTransactionParsingErrorException('Invalid file');
				}

				$text = $matches[1];
			} else {
				$text = preg_replace('/^.*\n/', '', $text);
			}

			$tablesData[] = $text;
		}

		$transactions = [];
		foreach ($tablesData as $singlePageData) {
			if ($singlePageData === null) {
				continue;
			}

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

		$datePattern = '/^\d{1,2}\.\s?\d{1,2}\.\s?\d{4}/';
		$pattern = '/Datum provedení Kód transakce Typ transakce Variabilní symbol Specifický symbol Konstantní symbol/';

		$parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if ($parts === false) {
			throw new KbPdfTransactionParsingErrorException('Invalid file');
		}

		$filteredParts = array_filter($parts, static fn (string $part) => Strings::trim($part) !== '');

		$currentTransaction = null;
		foreach ($filteredParts as $index => $filteredPart) {
			$filteredPart = Strings::trim($filteredPart);
			if ($index === 0) {
				$currentTransaction = new KbTransaction();
				preg_match($datePattern, $filteredPart, $matches);
				if (array_key_exists(0, $matches) === false) {
					throw new KbPdfTransactionParsingErrorException('Invalid file');
				}

				$currentTransaction->setSettlementDate($matches[0]);
				$currentTransaction->setTransactionDate($matches[0]);
				$currentTransaction->setTransactionRawContent($filteredPart);
				continue;
			}

			$filteredPart = Strings::trim($filteredPart);
			$lines = explode("\n", $filteredPart);
			$firstLine = array_shift($lines);
			$currentTransaction?->addTransactionRawContent($firstLine);

			// Slučení zbývajícího textu zpět do jednoho řetězce
			$remainingText = implode("\n", $lines);

			$previousLine = null;
			$transactionInfoOnNextLine = false;
			foreach (explode("\n", $remainingText) as $line) {
				if ((bool) preg_match($datePattern, $line) && $transactionInfoOnNextLine === false) {
					if ($currentTransaction !== null) {
						$transactions[] = $currentTransaction;
					}

					$currentTransaction = new KbTransaction();
					preg_match($datePattern, $line, $matches);
					if (array_key_exists(0, $matches) === false) {
						throw new KbPdfTransactionParsingErrorException('Invalid file');
					}

					$currentTransaction->setSettlementDate($matches[0]);
					$currentTransaction->setTransactionDate($matches[0]);
					$currentTransaction->setTransactionRawContent($line);
				} else {
					$currentTransaction?->addTransactionRawContent($line);
				}

				if (str_starts_with($line, 'Zpráva pro příjemce')) {
					if ($previousLine !== null && str_starts_with($previousLine, 'Popis pro mě') === false) {
						$transactionInfoOnNextLine = true;
					}
				} else {
					$transactionInfoOnNextLine = false;
				}

				$previousLine = $line;
			}
		}

		if ($currentTransaction !== null) {
			$transactions[] = $currentTransaction;
		}

		return $transactions;
	}

}
