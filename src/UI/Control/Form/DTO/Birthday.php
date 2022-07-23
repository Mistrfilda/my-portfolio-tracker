<?php

declare(strict_types = 1);

namespace App\UI\Control\Form\DTO;

use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class Birthday
{

	public function __construct(
		private int $day,
		private int $month,
		private int $year,
	)
	{
	}

	public function getDay(): int
	{
		return $this->day;
	}

	public function getMonth(): int
	{
		return $this->month;
	}

	public function getYear(): int
	{
		return $this->year;
	}

	public function getImmutableDatetime(): ImmutableDateTime
	{
		return DatetimeFactory::createFromFormat(
			'Y-m-d',
			sprintf('%d-%d-%d', $this->year, $this->month, $this->day),
		);
	}

}
