<?php

declare(strict_types = 1);

namespace App\Utils;

use Throwable;

class MonologHelper
{

	public static function formatMessageFromException(Throwable $value): string
	{
		return $value::class . ': ' . $value->getMessage() .
			($value->getCode() !== 0 ? ' #' . $value->getCode() : '') . ' in ' .
			$value->getFile() . ':' . $value->getLine();
	}

}
