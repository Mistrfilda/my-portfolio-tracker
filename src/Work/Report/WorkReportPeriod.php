<?php

declare(strict_types = 1);

namespace App\Work\Report;

use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

readonly class WorkReportPeriod
{

	public const TIMEZONE = 'Europe/Prague';

	private function __construct(
		public ImmutableDateTime $reportDate,
		public ImmutableDateTime $from,
		public ImmutableDateTime $to,
	)
	{
	}

	public static function create(string $reportDate, string|null $from = null, string|null $to = null): self
	{
		$date = self::parseDate($reportDate);
		if (($from === null) !== ($to === null)) {
			throw new InvalidArgumentException('Provide both --from and --to, or neither.');
		}

		$end = $to === null ? $date->deductDaysFromDatetime(1) : self::parseDate($to);
		$start = $from === null ? $end : self::parseDate($from);
		if ($from === null) {
			while ($start->isWeekend()) {
				$start = $start->deductDaysFromDatetime(1);
			}
		}

		if ($start > $end || $end >= $date) {
			throw new InvalidArgumentException('The report period must be ordered and end before the report date.');
		}

		return new self($date, $start, $end);
	}

	private static function parseDate(string $value): ImmutableDateTime
	{
		if (preg_match('~^\d{4}-\d{2}-\d{2}$~D', $value) !== 1) {
			throw new InvalidArgumentException('Dates must use YYYY-MM-DD format.');
		}

		$date = DatetimeFactory::createFromFormat($value . ' ' . self::TIMEZONE, '!Y-m-d e');
		if ($date->format('Y-m-d') !== $value) {
			throw new InvalidArgumentException('The specified date does not exist.');
		}

		return $date;
	}

}
