<?php

declare(strict_types = 1);

namespace App\Tax\Rules;

enum TaxExpenseFlatRateEnum: string
{

	case RATE_60 = 'rate_60';

	case RATE_80 = 'rate_80';

	case RATE_40 = 'rate_40';

	case RATE_30 = 'rate_30';

	public function getPercentage(): int
	{
		return match ($this) {
			self::RATE_80 => 80,
			self::RATE_60 => 60,
			self::RATE_40 => 40,
			self::RATE_30 => 30,
		};
	}

	public function getMaxExpensesCzk(): int
	{
		return match ($this) {
			self::RATE_80 => 1_600_000,
			self::RATE_60 => 1_200_000,
			self::RATE_40 => 800_000,
			self::RATE_30 => 600_000,
		};
	}

	public function format(): string
	{
		return match ($this) {
			self::RATE_60 => '60 % (max. 1 200 000 Kč) – běžná živnost',
			self::RATE_80 => '80 % (max. 1 600 000 Kč) – řemeslná živnost / zemědělství',
			self::RATE_40 => '40 % (max. 800 000 Kč) – jiná samostatná činnost',
			self::RATE_30 => '30 % (max. 600 000 Kč) – nájem majetku v obchodním majetku',
		};
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
