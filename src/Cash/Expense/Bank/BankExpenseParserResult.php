<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

interface BankExpenseParserResult
{

	/**
	 * @return array<BankExpenseData>
	 */
	public function getTransactions(): array;

	/**
	 * @return array<BankExpenseData>
	 */
	public function getUnprocessedTransactions(): array;

}
