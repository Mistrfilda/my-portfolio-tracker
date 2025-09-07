<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpenseParser;
use App\Utils\TypeValidator;
use Nette\Utils\Strings;
use const PHP_EOL;

class KbCsvCashParser implements BankExpenseParser
{

	public function parse(string $fileContents): KbContentParserResult
	{
		if (mb_detect_encoding($fileContents, 'UTF-8', true) === false) {
			$fileContents = iconv('Windows-1250', 'UTF-8//IGNORE', $fileContents);
			if ($fileContents === false) {
				throw new KbPdfTransactionParsingErrorException('Invalid file');
			}
		}

		$fileContents = preg_replace('/^\xEF\xBB\xBF/', '', $fileContents);

		$lines = explode(PHP_EOL, TypeValidator::validateString($fileContents));
		$headers = str_getcsv(array_shift($lines), ';', escape: '\\');

		$unprocessedTransactions = [];
		$processedTransactions = [];
		$incomingTransactions = [];

		foreach ($lines as $line) {
			$line = Strings::trim($line);
			if ($line === '') {
				continue;
			}

			$data = str_getcsv($line, ';', escape: '\\');
			//@phpstan-ignore-next-line
			$row = array_combine($headers, $data);
			$row['original_content'] = str_replace(';', ' ', $line);
			$type = $this->determineType($row);

			if ($type === null) {
				throw new KbPdfTransactionParsingErrorException(
					sprintf('Unknown type %s, fix it', $row['Typ transakce'] ?? ''),
				);
			}

			$amount = (int) $row['Castka'];
			$transaction = new KbTransaction();
			$transaction->setAmount($amount);
			$transaction->setBankTransactionType($type);
			$transaction->setTransactionRawContent($row['original_content']);
			$transaction->setSettlementDate($row['Datum zauctovani']);
			$transaction->setTransactionDate($row['Datum provedeni']);

			if ($amount > 0.0) {
				$incomingTransactions[] = $transaction;
			} else {
				$processedTransactions[] = $transaction;
			}
		}

		return new KbContentParserResult(
			$processedTransactions,
			$unprocessedTransactions,
			$incomingTransactions,
		);
	}

	/**
	 * @param array<string|null> $transactionParts
	 */
	private function determineType(array $transactionParts): BankTransactionType|null
	{
		$mappingConstant = KbBankTransactionType::MAPPING;
		foreach ($mappingConstant as $mapping) {
			foreach ($mapping['rawContentContains'] as $option) {
				if (str_contains($transactionParts['Typ transakce'] ?? '', $option)) {
					return $mapping['enum'];
				}
			}
		}

		return null;
	}

}
