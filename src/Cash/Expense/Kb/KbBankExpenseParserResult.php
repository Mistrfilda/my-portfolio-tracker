<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Kb;

use App\Cash\Expense\Bank\BankExpenseParserResult;

class KbBankExpenseParserResult implements BankExpenseParserResult
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
