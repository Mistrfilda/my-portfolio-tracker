<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxPaymentItemDTO
{

	public function __construct(
		public string $bankExpenseId,
		public string $date,
		public int $amountCzk,
		public string $roleCode,
		public string $roleLabel,
		public string|null $institutionCode,
		public int $assignedTaxYear,
		public bool $isYearOverridden,
		public bool $isRoleOverridden,
		public bool $countedAsAdvance,
		public string $description,
	)
	{
	}

}
