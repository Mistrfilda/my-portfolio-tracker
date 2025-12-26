<?php

declare(strict_types = 1);

namespace App\Test\Unit\Currency;

use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\NoResultException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;

class CurrencyConversionRepositoryTest extends TestCase
{

	public function testFindCurrencyPairConversionForClosestDateExactMatch(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15');
		$expectedConversion = $this->createMockConversion($searchDate, 25.0);

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->with(CurrencyEnum::USD, CurrencyEnum::CZK, $searchDate)
			->andReturn($expectedConversion);

		$result = $repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);

		$this->assertSame($expectedConversion, $result);
	}

	public function testFindCurrencyPairConversionForClosestDateCloserBefore(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15 12:00:00');
		$beforeConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-15 10:00:00'), 25.0);
		$afterConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-15 16:00:00'), 25.5);

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestBefore')
			->once()
			->andReturn($beforeConversion);

		$repository
			->shouldReceive('findClosestAfter')
			->once()
			->andReturn($afterConversion);

		$result = $repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);

		// Before is 2 hours away, after is 4 hours away - should return before
		$this->assertSame($beforeConversion, $result);
	}

	public function testFindCurrencyPairConversionForClosestDateCloserAfter(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15 12:00:00');
		$beforeConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-14 12:00:00'), 25.0);
		$afterConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-15 14:00:00'), 25.5);

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestBefore')
			->once()
			->andReturn($beforeConversion);

		$repository
			->shouldReceive('findClosestAfter')
			->once()
			->andReturn($afterConversion);

		$result = $repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);

		// Before is 24 hours away, after is 2 hours away - should return after
		$this->assertSame($afterConversion, $result);
	}

	public function testFindCurrencyPairConversionForClosestDateEqualDistancePrefersBefore(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15 12:00:00');
		$beforeConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-15 10:00:00'), 25.0);
		$afterConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-15 14:00:00'), 25.5);

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestBefore')
			->once()
			->andReturn($beforeConversion);

		$repository
			->shouldReceive('findClosestAfter')
			->once()
			->andReturn($afterConversion);

		$result = $repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);

		// Both are 2 hours away - should prefer before (<=)
		$this->assertSame($beforeConversion, $result);
	}

	public function testFindCurrencyPairConversionForClosestDateOnlyBefore(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15');
		$beforeConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-10'), 25.0);

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestBefore')
			->once()
			->andReturn($beforeConversion);

		$repository
			->shouldReceive('findClosestAfter')
			->once()
			->andReturn(null);

		$result = $repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);

		$this->assertSame($beforeConversion, $result);
	}

	public function testFindCurrencyPairConversionForClosestDateOnlyAfter(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15');
		$afterConversion = $this->createMockConversion(new ImmutableDateTime('2024-01-20'), 25.5);

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestBefore')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestAfter')
			->once()
			->andReturn($afterConversion);

		$result = $repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);

		$this->assertSame($afterConversion, $result);
	}

	public function testFindCurrencyPairConversionForClosestDateNoResultsThrowsException(): void
	{
		$repository = Mockery::mock(CurrencyConversionRepository::class)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$searchDate = new ImmutableDateTime('2024-01-15');

		$repository
			->shouldReceive('findCurrencyPairConversionForDate')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestBefore')
			->once()
			->andReturn(null);

		$repository
			->shouldReceive('findClosestAfter')
			->once()
			->andReturn(null);

		$this->expectException(NoResultException::class);

		$repository->findCurrencyPairConversionForClosestDate(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
			$searchDate,
		);
	}

	private function createMockConversion(ImmutableDateTime $date, float $rate): CurrencyConversion
	{
		$mock = Mockery::mock(CurrencyConversion::class);
		$mock
			->shouldReceive('getForDate')
			->andReturn($date);

		return $mock;
	}

}
