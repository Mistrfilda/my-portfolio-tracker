<?php

declare(strict_types = 1);

namespace App\Goal;

use App\Currency\CurrencyEnum;
use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum PortfolioGoalTypeEnum: string implements DatagridRenderableEnum
{

	case TOTAL_INVESTED_AMOUNT = 'total_invested_amount';

	case TOTAL_INCOME = 'total_income';

	public function format(): string
	{
		return match ($this) {
			self::TOTAL_INVESTED_AMOUNT => 'Investovaná částka',
			self::TOTAL_INCOME => 'Přijem',
		};
	}

	public function getCurrency(): CurrencyEnum
	{
		return match ($this) {
			self::TOTAL_INVESTED_AMOUNT, self::TOTAL_INCOME => CurrencyEnum::CZK,
		};
	}

}
