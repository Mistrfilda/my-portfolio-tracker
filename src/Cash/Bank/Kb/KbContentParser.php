<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;

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

			$amount = $this->parseAmount(array_pop($transactionParts));

			if ($amount === 0.0) {
				$transaction->setUnprocessedReason('Invalid amount, cant parse amount');
				$unprocessedTransactions[] = $transaction;
				continue;
			}

			$transaction->setAmount($amount);

			$type = $this->determineType(
				$transactionParts,
				$transaction->getTransactionRawContent() ?? '',
			);

			if ($amount > 0.0) {
				$transaction->setBankTransactionType($type ?? BankTransactionType::TRANSACTION);
				$incomingTransactions[] = $transaction;
				continue;
			}

			if ($type === null) {
				dump($transaction);
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

	private function parseAmount(string $part): float
	{
		$parts = explode(' ', $part);
		if (count($parts) <= 2) {
			return (float) str_replace(' ', '', $part);
		}

		$last = array_pop($parts);
		$beforeLast = array_pop($parts);
		$amount = $beforeLast . $last;

		return (float) $amount;
	}

	/**
	 * @param array<string> $transactionParts
	 */
	private function determineType(array $transactionParts, string $rawContent): BankTransactionType|null
	{
		$firstLine = reset($transactionParts);
		if ($firstLine === false) {
			throw new KbPdfTransactionParsingErrorException('Invalid transaction parts');
		}

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

			foreach ($mapping['firstLineContains'] as $option) {
				if (str_contains($rawContent, $option)) {
					return $mapping['enum'];
				}
			}
		}

		return null;
	}

}
