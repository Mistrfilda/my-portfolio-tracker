<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Kb;

use App\Cash\Expense\Bank\BankTransactionType;

class KbBankTransactionType
{

	public const array MAPPING = [
		BankTransactionType::CARD_PAYMENT->value => [
			'enum' => BankTransactionType::CARD_PAYMENT,
			'firstLineEq' => ['TRANSAKCE PLATEBNÍ KARTOU', 'Platba/Výběr hotovosti platební kartou'],
			'firstLineContains' => [],
			'rawContentContains' => [],
		],
		BankTransactionType::REPEATING_TRANSACTION->value => [
			'enum' => BankTransactionType::REPEATING_TRANSACTION,
			'firstLineEq' => ['TRVALÝ PŘÍKAZ - ČTRNÁCTIDENNÍ', 'PLATBA SCV'],
			'firstLineContains' => [],
			'rawContentContains' => [],
		],
		BankTransactionType::TRANSACTION->value => [
			'enum' => BankTransactionType::TRANSACTION,
			'firstLineEq' => ['OKAMŽITÁ ODCHOZÍ ÚHRADA', 'ODCHOZÍ ÚHRADA'],
			'firstLineContains' => [],
			'rawContentContains' => [],
		],
		BankTransactionType::BANK_FEE->value => [
			'enum' => BankTransactionType::BANK_FEE,
			'firstLineEq' => ['Výběr z bankomatu - poplatek'],
			'firstLineContains' => [],
			'rawContentContains' => [],
		],
	];

}
