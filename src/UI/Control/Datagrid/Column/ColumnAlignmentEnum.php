<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Column;

enum ColumnAlignmentEnum: string
{

	case LEFT = 'left';

	case CENTER = 'center';

	case RIGHT = 'right';

	public function getCellClass(): string
	{
		return match ($this) {
			self::LEFT => 'text-left',
			self::CENTER => 'text-center',
			self::RIGHT => 'text-right',
		};
	}

}
