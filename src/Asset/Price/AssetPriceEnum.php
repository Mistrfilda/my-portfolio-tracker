<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

enum AssetPriceEnum: string
{

	case SAME = 'same';

	case UP = 'up';

	case DOWN = 'down';

	public function getSvgIcon(): SvgIcon
	{
		return match ($this) {
			AssetPriceEnum::UP => SvgIcon::ARROW_UP,
			AssetPriceEnum::DOWN => SvgIcon::ARROW_DOWN,
			AssetPriceEnum::SAME => SvgIcon::ADJUSTMENTS
		};
	}

	public function getTailwindColor(): string
	{
		return match ($this) {
			AssetPriceEnum::UP => TailwindColorConstant::GREEN,
			AssetPriceEnum::DOWN => TailwindColorConstant::RED,
			AssetPriceEnum::SAME => TailwindColorConstant::GRAY
		};
	}

}
