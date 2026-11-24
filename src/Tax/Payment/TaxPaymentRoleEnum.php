<?php

declare(strict_types = 1);

namespace App\Tax\Payment;

use App\Tax\Rules\TaxInstitutionEnum;

enum TaxPaymentRoleEnum: string
{

	case INCOME_TAX_ADVANCE = 'income_tax_advance';

	case INCOME_TAX_SETTLEMENT = 'income_tax_settlement';

	case HEALTH_INSURANCE_ADVANCE = 'health_insurance_advance';

	case HEALTH_INSURANCE_SETTLEMENT = 'health_insurance_settlement';

	case SOCIAL_INSURANCE_ADVANCE = 'social_insurance_advance';

	case SOCIAL_INSURANCE_SETTLEMENT = 'social_insurance_settlement';

	case SICKNESS_INSURANCE = 'sickness_insurance';

	case CHARITY_DONATION = 'charity_donation';

	case PENALTY = 'penalty';

	case OTHER_TAX = 'other_tax';

	public function format(): string
	{
		return match ($this) {
			self::INCOME_TAX_ADVANCE => 'FÚ – záloha na daň z příjmů',
			self::INCOME_TAX_SETTLEMENT => 'FÚ – doplatek daně',
			self::HEALTH_INSURANCE_ADVANCE => 'Zdravotní pojišťovna – záloha',
			self::HEALTH_INSURANCE_SETTLEMENT => 'Zdravotní pojišťovna – doplatek',
			self::SOCIAL_INSURANCE_ADVANCE => 'ČSSZ – záloha na důchodové pojištění',
			self::SOCIAL_INSURANCE_SETTLEMENT => 'ČSSZ – doplatek',
			self::SICKNESS_INSURANCE => 'ČSSZ – dobrovolné nemocenské',
			self::CHARITY_DONATION => 'Dar (darujme.cz, donio.cz apod.)',
			self::PENALTY => 'Penále / úrok z prodlení',
			self::OTHER_TAX => 'Ostatní daň / poplatek',
		};
	}

	public function getInstitution(): TaxInstitutionEnum|null
	{
		return match ($this) {
			self::INCOME_TAX_ADVANCE,
			self::INCOME_TAX_SETTLEMENT => TaxInstitutionEnum::TAX_OFFICE,
			self::HEALTH_INSURANCE_ADVANCE,
			self::HEALTH_INSURANCE_SETTLEMENT => TaxInstitutionEnum::HEALTH_INSURANCE,
			self::SOCIAL_INSURANCE_ADVANCE,
			self::SOCIAL_INSURANCE_SETTLEMENT,
			self::SICKNESS_INSURANCE => TaxInstitutionEnum::SOCIAL_INSURANCE,
			self::CHARITY_DONATION,
			self::PENALTY,
			self::OTHER_TAX => null,
		};
	}

	public function isCountedAsAdvance(): bool
	{
		return match ($this) {
			self::INCOME_TAX_ADVANCE,
			self::HEALTH_INSURANCE_ADVANCE,
			self::SOCIAL_INSURANCE_ADVANCE => true,
			default => false,
		};
	}

	public function isDonation(): bool
	{
		return $this === self::CHARITY_DONATION;
	}

	public function isTaxPayment(): bool
	{
		return $this !== self::CHARITY_DONATION;
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		$options = [];
		foreach (self::cases() as $case) {
			$options[$case->value] = $case->format();
		}

		return $options;
	}

}
