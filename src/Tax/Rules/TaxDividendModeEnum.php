<?php

declare(strict_types = 1);

namespace App\Tax\Rules;

enum TaxDividendModeEnum: string
{

	case STANDARD_SECTION_8 = 'standard_section_8';

	case SEPARATE_BASE_16A = 'separate_base_16a';

	public function format(): string
	{
		return match ($this) {
			self::STANDARD_SECTION_8 => 'Běžný základ § 8 + příloha č. 3',
			self::SEPARATE_BASE_16A => 'Samostatný základ 15 % (§ 16a) + příloha č. 4',
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
