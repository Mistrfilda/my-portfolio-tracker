<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum BankAccountTypeEnum: string implements DatagridRenderableEnum
{

	case PERSONAL = 'personal';
	case BUSINESS = 'business';

	public function format(): string
	{
		return match ($this) {
			BankAccountTypeEnum::BUSINESS => 'Podnikatelský',
			BankAccountTypeEnum::PERSONAL => 'Osobní',
		};
	}

}
