<?php

declare(strict_types = 1);

namespace App\Test\Unit\System;

use App\System\Resolver\SystemValueCurrentVersionResolver;
use App\System\Resolver\SystemValueDatabaseResolver;
use App\System\SystemValue;
use App\System\SystemValueEnum;
use App\System\SystemValueRepository;
use App\System\SystemValueResolveFacade;
use App\System\SystemValuesDataBag;
use App\UI\Control\Datagrid\Datagrid;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;

class SystemValueResolveFacadeTest extends TestCase
{

	public function testGetAllValues(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$systemValueMock = Mockery::mock(SystemValue::class);

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->andReturn($systemValueMock);

		$systemValueMock
			->shouldReceive('getStringValue')
			->andReturn(null);

		$systemValueMock
			->shouldReceive('getIntValue')
			->andReturn(42);

		$systemValueMock
			->shouldReceive('getDatetimeValue')
			->andReturn(null);

		$resolver = new SystemValueDatabaseResolver($systemValueRepositoryMock);
		$systemValueResolveFacade = new SystemValueResolveFacade([$resolver]);

		$values = $systemValueResolveFacade->getAllValues();

		$this->assertIsArray($values);
		$this->assertNotEmpty($values);
	}

	public function testGetValueReturnsFormattedString(): void
	{
		$systemValuesDataBagMock = Mockery::mock(SystemValuesDataBag::class);
		$systemValuesDataBagMock
			->shouldReceive('getParameter')
			->with('versionFile')
			->andReturn(__DIR__ . '/deploy-versions.txt');

		$resolver = new SystemValueCurrentVersionResolver($systemValuesDataBagMock);
		$systemValueResolveFacade = new SystemValueResolveFacade([$resolver]);

		$enum = SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION;
		$result = $systemValueResolveFacade->getValue($enum);

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
	}

	public function testGetValueReturnsFormattedInt(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$systemValueMock = Mockery::mock(SystemValue::class);

		$enum = SystemValueEnum::DIVIDENDS_UPDATED_COUNT;
		$expectedValue = 42;

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->with($enum)
			->once()
			->andReturn($systemValueMock);

		$systemValueMock
			->shouldReceive('getStringValue')
			->andReturn(null);

		$systemValueMock
			->shouldReceive('getIntValue')
			->andReturn($expectedValue);

		$resolver = new SystemValueDatabaseResolver($systemValueRepositoryMock);
		$systemValueResolveFacade = new SystemValueResolveFacade([$resolver]);

		$result = $systemValueResolveFacade->getValue($enum);

		$this->assertEquals('42', $result);
	}

	public function testGetValueReturnsFormattedDatetime(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$systemValueMock = Mockery::mock(SystemValue::class);

		$enum = SystemValueEnum::DIVIDENDS_UPDATED_AT;
		$datetime = new ImmutableDateTime('2025-12-29 10:00:00');

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->with($enum)
			->once()
			->andReturn($systemValueMock);

		$systemValueMock
			->shouldReceive('getStringValue')
			->andReturn(null);

		$systemValueMock
			->shouldReceive('getIntValue')
			->andReturn(null);

		$systemValueMock
			->shouldReceive('getDatetimeValue')
			->andReturn($datetime);

		$resolver = new SystemValueDatabaseResolver($systemValueRepositoryMock);
		$systemValueResolveFacade = new SystemValueResolveFacade([$resolver]);

		$result = $systemValueResolveFacade->getValue($enum);

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
	}

	public function testGetValueReturnsPlaceholderForNull(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);

		$enum = SystemValueEnum::DIVIDENDS_UPDATED_AT;

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->with($enum)
			->once()
			->andReturn(null);

		$resolver = new SystemValueDatabaseResolver($systemValueRepositoryMock);
		$systemValueResolveFacade = new SystemValueResolveFacade([$resolver]);

		$result = $systemValueResolveFacade->getValue($enum);

		$this->assertEquals(Datagrid::NULLABLE_PLACEHOLDER, $result);
	}

	public function testGetValueThrowsExceptionWhenResolverNotFound(): void
	{
		$systemValueResolveFacade = new SystemValueResolveFacade([]);

		$enum = SystemValueEnum::DIVIDENDS_UPDATED_AT;

		$this->expectException(InvalidArgumentException::class);

		$systemValueResolveFacade->getValue($enum);
	}

	public function testGetAllValuesByEnumType(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$systemValueMock = Mockery::mock(SystemValue::class);

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->andReturn($systemValueMock);

		$systemValueMock
			->shouldReceive('getStringValue')
			->andReturn(null);

		$systemValueMock
			->shouldReceive('getIntValue')
			->andReturn(42);

		$systemValueMock
			->shouldReceive('getDatetimeValue')
			->andReturn(null);

		$resolver = new SystemValueDatabaseResolver($systemValueRepositoryMock);
		$systemValueResolveFacade = new SystemValueResolveFacade([$resolver]);

		$values = $systemValueResolveFacade->getAllValuesByEnumType();

		$this->assertIsArray($values);
		$this->assertNotEmpty($values);

		// Verify that keys are enum values, not labels
		foreach ($values as $key => $value) {
			$this->assertIsString($key);
			$this->assertIsString($value);
		}
	}

}
