<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Work\Report\WorkReportPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkReportPeriodTest extends TestCase
{

	#[DataProvider('validPeriods')]
	public function testDefaultPeriodCoversDaysSincePreviousWeekday(string $date, string $from, string $to): void
	{
		$period = WorkReportPeriod::create($date);
		self::assertSame($from, $period->from->format('Y-m-d'));
		self::assertSame($to, $period->to->format('Y-m-d'));
		self::assertSame('Europe/Prague', $period->reportDate->getTimezone()->getName());
	}

	/** @return iterable<string, array{string, string, string}> */
	public static function validPeriods(): iterable
	{
		yield 'Tuesday' => ['2026-10-27', '2026-10-26', '2026-10-26'];
		yield 'Monday' => ['2026-10-26', '2026-10-23', '2026-10-25'];
		yield 'year boundary' => ['2026-01-05', '2026-01-02', '2026-01-04'];
		yield 'DST change' => ['2026-03-30', '2026-03-27', '2026-03-29'];
	}

	public function testExplicitRangeSupportsHolidaysAndBackfills(): void
	{
		$period = WorkReportPeriod::create('2026-10-29', '2026-10-26', '2026-10-28');
		self::assertSame('2026-10-26', $period->from->format('Y-m-d'));
		self::assertSame('2026-10-28', $period->to->format('Y-m-d'));
	}

	public function testDateParsingIsIndependentOfMachineTimezone(): void
	{
		$original = date_default_timezone_get();
		try {
			date_default_timezone_set('Asia/Tokyo');
			$period = WorkReportPeriod::create('2026-10-27');
			self::assertSame('2026-10-27 00:00:00+01:00', $period->reportDate->format('Y-m-d H:i:sP'));
		} finally {
			date_default_timezone_set($original);
		}
	}

	#[DataProvider('invalidPeriods')]
	public function testInvalidDatesAndRangesAreRejected(string $date, string|null $from, string|null $to): void
	{
		$this->expectException(InvalidArgumentException::class);
		WorkReportPeriod::create($date, $from, $to);
	}

	/** @return iterable<string, array{string, string|null, string|null}> */
	public static function invalidPeriods(): iterable
	{
		yield 'relative date' => ['yesterday', null, null];
		yield 'invalid calendar date' => ['2026-02-30', null, null];
		yield 'missing end' => ['2026-10-27', '2026-10-23', null];
		yield 'missing start' => ['2026-10-27', null, '2026-10-26'];
		yield 'reversed' => ['2026-10-27', '2026-10-26', '2026-10-23'];
		yield 'unfinished day' => ['2026-10-27', '2026-10-26', '2026-10-27'];
	}

}
