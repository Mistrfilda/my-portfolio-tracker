<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;

class KbBankTransactionType
{

	public const array MAPPING = [
		BankTransactionType::CARD_PAYMENT->value => [
			'enum' => BankTransactionType::CARD_PAYMENT,
			'firstLineEq' => ['TRANSAKCE PLATEBNÍ KARTOU', 'Platba/Výběr hotovosti platební kartou'],
			'firstLineContains' => [],
			'rawContentContains' => ['Mobilní platba', 'Nákup na internetu', 'Výběr hotovosti z'],
		],
		BankTransactionType::REPEATING_TRANSACTION->value => [
			'enum' => BankTransactionType::REPEATING_TRANSACTION,
			'firstLineEq' => ['TRVALÝ PŘÍKAZ - ČTRNÁCTIDENNÍ', 'PLATBA SCV'],
			'firstLineContains' => [],
			'rawContentContains' => ['Opakovaná platba', 'TRVALÝ PŘÍKAZ - ČTRNÁCTIDENNÍ'],
		],
		BankTransactionType::TRANSACTION->value => [
			'enum' => BankTransactionType::TRANSACTION,
			'firstLineEq' => ['OKAMŽITÁ ODCHOZÍ ÚHRADA', 'ODCHOZÍ ÚHRADA'],
			'firstLineContains' => [],
			'rawContentContains' => ['Odchozí úhrada', 'Trvalý příkaz'],
		],
		BankTransactionType::BANK_FEE->value => [
			'enum' => BankTransactionType::BANK_FEE,
			'firstLineEq' => ['Výběr z bankomatu - poplatek'],
			'firstLineContains' => [],
			'rawContentContains' => ['Poplatek za'],
		],
	];

}
