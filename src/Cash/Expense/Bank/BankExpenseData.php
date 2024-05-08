<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Cash\Bank\BankTransactionType;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface BankExpenseData
{

	public function getBankTransactionType(): BankTransactionType;

	public function getSettlementDate(): ImmutableDateTime|null;

	public function getTransactionDate(): ImmutableDateTime|null;

}
