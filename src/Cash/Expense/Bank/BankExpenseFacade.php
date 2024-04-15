<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

interface BankExpenseFacade
{

	public function processFileContents(string $fileContents): bool;

}
