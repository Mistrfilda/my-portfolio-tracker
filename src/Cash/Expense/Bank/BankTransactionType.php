<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Cash\Expense\ExpenseTypeEnum;
use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum BankTransactionType: string implements ExpenseTypeEnum, DatagridRenderableEnum
{

	case CARD_PAYMENT = 'CARD_PAYMENT';

	case REPEATING_TRANSACTION = 'REPEATING_TRANSACTION';

	case TRANSACTION = 'TRANSACTION';

	case BANK_FEE = 'BANK_FEE';

	public function format(): string
	{
		return match ($this) {
			BankTransactionType::CARD_PAYMENT => 'Platba kartou',
			BankTransactionType::REPEATING_TRANSACTION => 'Trvalý příkaz',
			BankTransactionType::TRANSACTION => 'Odchozí platba',
			BankTransactionType::BANK_FEE => 'Bankovní poplatek',
		};
	}

}
