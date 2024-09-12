<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;
use InvalidArgumentException;
use Nette\Utils\Strings;

class KbContentParser
{

	/**
	 * @param array<KbTransaction> $transactions
	 */
	public function processRawKbTransactions(array $transactions): KbContentParserResult
	{
		$unprocessedTransactions = [];
		$processedTransactions = [];
		$incomingTransactions = [];

		foreach ($transactions as $transaction) {
			$transactionParts = preg_split('~\n~', $transaction->getTransactionRawContent() ?? '');

			if ($transactionParts === false) {
				throw new KbPdfTransactionParsingErrorException('Invalid transaction parts');
			}

			if (count($transactionParts) === 0) {
				$transaction->setUnprocessedReason('Invalid lines, cant parse transaction into parts');
				$unprocessedTransactions[] = $transaction;
				continue;
			}

			$amount = $this->parseAmount($transactionParts);

			if ($amount === 0.0) {
				$transaction->setUnprocessedReason('Invalid amount, cant parse amount');
				$unprocessedTransactions[] = $transaction;
				continue;
			}

			$transaction->setAmount($amount);
			if ($amount > 0.0) {
				$transaction->setBankTransactionType($type ?? BankTransactionType::TRANSACTION);
				$incomingTransactions[] = $transaction;
				continue;
			}

			$type = $this->determineType(
				$transactionParts,
				$transaction->getTransactionRawContent() ?? '',
			);

			if ($type === null) {
				continue;
			}

			$transaction->setBankTransactionType($type);
			$processedTransactions[] = $transaction;
		}

		return new KbContentParserResult(
			$processedTransactions,
			$unprocessedTransactions,
			$incomingTransactions,
		);
	}

	/**
	 * @param array<string> $parts
	 */
	private function parseAmount(array $parts): float
	{
		$pattern = '/([+-]?\d{1,3}(?:[ .,]\d{3})*(?:[\.,]\d{1,2})?)\s*(Kč|CZK)/';

		foreach ($parts as $part) {
			if ((bool) preg_match($pattern, Strings::trim($part), $matches)) {
				$price = $matches[1];
				$price = Strings::trim(str_replace(',', '.', $price));
				$price = str_replace(' ', '', $price);
				return (float) $price;
			}
		}

		throw new InvalidArgumentException();
	}

	/**
	 * @param array<string> $transactionParts
	 */
	private function determineType(array $transactionParts, string $rawContent): BankTransactionType|null
	{
		if (count($transactionParts) === 0) {
			return null;
		}

		$firstLine = reset($transactionParts);
		$rawContent = str_replace("\n", '', $rawContent);

		/**
		 * @var array<string, array{
		 *       enum: BankTransactionType,
		 *       firstLineEq: array<string>,
		 *       firstLineContains: array<string>,
		 *       rawContentContains: array<string>
		 *  }> $mappingConstant
		 */
		$mappingConstant = KbBankTransactionType::MAPPING;
		foreach ($mappingConstant as $mapping) {
			foreach ($mapping['firstLineEq'] as $option) {
				if ($firstLine === $option) {
					return $mapping['enum'];
				}
			}

			foreach ($mapping['firstLineContains'] as $option) {
				if (str_contains($firstLine, $option)) {
					return $mapping['enum'];
				}
			}

			foreach ($mapping['rawContentContains'] as $option) {
				if (str_contains($rawContent, $option)) {
					return $mapping['enum'];
				}
			}
		}

		return null;
	}

}
