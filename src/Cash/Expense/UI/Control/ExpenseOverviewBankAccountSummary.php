<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Bank\Account\BankAccount;

class ExpenseOverviewBankAccountSummary
{

	public function __construct(
		private BankAccount $bankAccount,
		private SummaryPrice $summaryPrice,
	)
	{
	}

	public function getBankAccount(): BankAccount
	{
		return $this->bankAccount;
	}

	public function getSummaryPrice(): SummaryPrice
	{
		return $this->summaryPrice;
	}

}
