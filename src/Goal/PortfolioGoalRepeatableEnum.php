<?php

declare(strict_types = 1);

namespace App\Goal;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum PortfolioGoalRepeatableEnum: string implements DatagridRenderableEnum
{

	case MONTHLY = 'monthly';

	case WEEKLY = 'weekly';

	public function format(): string
	{
		return match ($this) {
			self::WEEKLY => 'Týdně',
			self::MONTHLY => 'Měsíčně'
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getFormOptions(): array
	{
		return [
			PortfolioGoalRepeatableEnum::WEEKLY->value => 'Týdně',
			PortfolioGoalRepeatableEnum::MONTHLY->value => 'Měsíčně',
		];
	}

}
