<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use InvalidArgumentException;
use Nette\Utils\Json;

class PortfolioPeriodStatisticJson
{

	public static function encode(object $value): string
	{
		return Json::encode($value);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T
	 */
	public static function decode(string $json, string $class): object
	{
		$data = Json::decode($json, forceArrays: true);
		if (!is_array($data)) {
			throw new InvalidArgumentException('Portfolio period statistic JSON must contain an object.');
		}

		return new MapperBuilder()
			->allowPermissiveTypes()
			->mapper()
			->map($class, Source::array($data));
	}

}
