<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Expense\Bank\BankExpenseParserResult;

class KbContentParserResult implements BankExpenseParserResult
{

	/**
	 * @param array<KbTransaction> $processedTransactions
	 * @param array<KbTransaction> $unprocessedTransactions
	 * @param array<KbTransaction> $incomingTransactions
	 */
	public function __construct(
		private array $processedTransactions,
		private array $unprocessedTransactions,
		private array $incomingTransactions,
	)
	{
	}

	/**
	 * @return array<KbTransaction>
	 */
	public function getUnprocessedTransactions(): array
	{
		return $this->unprocessedTransactions;
	}

	/**
	 * @return array<KbTransaction>
	 */
	public function getProcessedTransactions(): array
	{
		return $this->processedTransactions;
	}

	/**
	 * @return array<KbTransaction>
	 */
	public function getIncomingTransactions(): array
	{
		return $this->incomingTransactions;
	}

	/**
	 * @return array<KbTransaction>
	 */
	public function getTransactions(): array
	{
		return $this->processedTransactions;
	}

}
