<?php

declare(strict_types = 1);

namespace App\Cash\Expense;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface Expense
{

	public function getDate(): ImmutableDateTime;

	public function getExpensePrice(): ExpensePrice;

	public function getExpenseType(): ExpenseTypeEnum;

	public function getIdentifier(): string;

}
