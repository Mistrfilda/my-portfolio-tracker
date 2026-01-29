<?php

declare(strict_types = 1);

namespace App\Home\Device;

enum HomeDeviceType: string
{

	case TEMPERATURE = 'temperature';

	public function format(): string
	{
		return match ($this) {
			self::TEMPERATURE => 'Teplotní senzor',
		};
	}

}
