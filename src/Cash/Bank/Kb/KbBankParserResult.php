<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Expense\Bank\BankExpenseParserResult;

class KbBankParserResult implements BankExpenseParserResult
{

	/**
	 * @param array<KbTransaction> $transactions
	 */
	public function __construct(private array $transactions)
	{

	}

	/**
	 * @return array<KbTransaction>
	 */
	public function getTransactions(): array
	{
		return $this->transactions;
	}

}
