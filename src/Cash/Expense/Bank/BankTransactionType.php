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

	case TAX = 'TAX';

	public function format(): string
	{
		return match ($this) {
			BankTransactionType::CARD_PAYMENT => 'Platba kartou',
			BankTransactionType::REPEATING_TRANSACTION => 'Trvalý příkaz',
			BankTransactionType::TRANSACTION => 'Odchozí platba',
			BankTransactionType::BANK_FEE => 'Bankovní poplatek',
			BankTransactionType::TAX => 'Daň',
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			BankTransactionType::CARD_PAYMENT->value => 'Platba kartou',
			BankTransactionType::REPEATING_TRANSACTION->value => 'Trvalý příkaz',
			BankTransactionType::TRANSACTION->value => 'Odchozí platba',
			BankTransactionType::BANK_FEE->value => 'Bankovní poplatek',
			BankTransactionType::TAX->value => 'Daň',
		];
	}

}
