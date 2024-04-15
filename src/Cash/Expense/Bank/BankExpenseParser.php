<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

interface BankExpenseParser
{

	public function parse(string $fileContents): BankExpenseParserResult;

}
