<?php

declare(strict_types = 1);

namespace App\Home\Device\Record;

enum HomeDeviceRecordUnit: string
{

	case CELSIUS = 'celsius';

	public function format(): string
	{
		return match ($this) {
			self::CELSIUS => '°C',
		};
	}

}
