<?php

declare(strict_types = 1);

namespace App\Tax\Rules;

enum TaxInstitutionEnum: string
{

	case TAX_OFFICE = 'tax_office';

	case HEALTH_INSURANCE = 'health_insurance';

	case SOCIAL_INSURANCE = 'social_insurance';

	public function format(): string
	{
		return match ($this) {
			self::TAX_OFFICE => 'Finanční úřad (DPFO)',
			self::HEALTH_INSURANCE => 'Zdravotní pojišťovna',
			self::SOCIAL_INSURANCE => 'ČSSZ (důchodové pojištění)',
		};
	}

	public function formatShort(): string
	{
		return match ($this) {
			self::TAX_OFFICE => 'FÚ',
			self::HEALTH_INSURANCE => 'Zdravotní pojišťovna',
			self::SOCIAL_INSURANCE => 'ČSSZ',
		};
	}

}
