<?php

declare(strict_types = 1);

namespace App\Currency;

class GBPCurrencyHelper
{

	public static function formatGBpToGBP(float $value): float
	{
		return $value / 100;
	}

}
