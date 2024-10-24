<?php

declare(strict_types = 1);

namespace App\System;

use App\System\Exception\SystemValueDataBagMissingPropertyException;

class SystemValuesDataBag
{

	/**
	 * @param array<string, string|int|float> $systemValuesParameters
	 */
	public function __construct(private array $systemValuesParameters)
	{
	}

	public function getParameter(string $key): string|int|float
	{
		if (array_key_exists($key, $this->systemValuesParameters) === false) {
			throw new SystemValueDataBagMissingPropertyException();
		}

		return $this->systemValuesParameters[$key];
	}

}
