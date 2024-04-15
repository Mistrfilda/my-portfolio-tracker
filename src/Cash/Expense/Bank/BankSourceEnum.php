<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum BankSourceEnum: string implements DatagridRenderableEnum
{

	case KOMERCNI_BANKA = 'KOMERCNI_BANKA';

	public function format(): string
	{
		return match ($this) {
			BankSourceEnum::KOMERCNI_BANKA => 'Komerční banka',
		};
	}

}
