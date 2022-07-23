<?php

declare(strict_types = 1);

namespace App\UI\FlashMessage;

use App\UI\Tailwind\TailwindColorConstant;

class FlashMessageType
{

	public const INFO = 'info';

	public const WARNING = 'warning';

	public const SUCCESS = 'success';

	public const DANGER = 'danger';

	public const ALL = [
		self::INFO,
		self::WARNING,
		self::SUCCESS,
		self::DANGER,
	];

	public const COLOR_MAPING = [
		self::INFO => TailwindColorConstant::BLUE,
		self::WARNING => TailwindColorConstant::YELLOW,
		self::SUCCESS => TailwindColorConstant::GREEN,
		self::DANGER => TailwindColorConstant::RED,
	];

	public static function flashMessageTypeExists(string $type): void
	{
		if (in_array($type, self::ALL, true) === false) {
			throw new InvalidFlashMessageTypeException();
		}
	}

	public static function getColorForFlashMessage(string $type): string
	{
		self::flashMessageTypeExists($type);

		return self::COLOR_MAPING[$type];
	}

}
