<?php

declare(strict_types = 1);

namespace App\Test\Unit\System;

use App\System\Exception\SystemValueInvalidArgumentException;
use App\System\SystemValue;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\System\SystemValueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;

class SystemValueFacadeTest extends TestCase
{

	public function testUpdateValueCreateNew(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);

		$systemValueFacade = new SystemValueFacade(
			$systemValueRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
		);

		$now = new ImmutableDateTime('now');
		$enum = SystemValueEnum::DIVIDENDS_UPDATED_COUNT;
		$intValue = 42;

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->with($enum)
			->once()
			->andReturn(null);

		$datetimeFactoryMock
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock
			->shouldReceive('persist')
			->once()
			->withArgs(static fn ($systemValue) => $systemValue instanceof SystemValue
					&& $systemValue->getSystemValueEnum() === $enum
					&& $systemValue->getIntValue() === $intValue
					&& $systemValue->getDatetimeValue() === null
					&& $systemValue->getStringValue() === null);

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$systemValueFacade->updateValue($enum, null, $intValue, null);

		$this->assertTrue(true);
	}

	public function testUpdateValueUpdateExisting(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);

		$systemValueFacade = new SystemValueFacade(
			$systemValueRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
		);

		$now = new ImmutableDateTime('now');
		$enum = SystemValueEnum::DIVIDENDS_UPDATED_AT;
		$datetimeValue = new ImmutableDateTime('2025-12-29 10:00:00');

		$existingValueMock = Mockery::mock(SystemValue::class);

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->with($enum)
			->once()
			->andReturn($existingValueMock);

		$datetimeFactoryMock
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$existingValueMock
			->shouldReceive('update')
			->once()
			->with($now, $datetimeValue, null, null);

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$systemValueFacade->updateValue($enum, $datetimeValue, null, null);

		$this->assertTrue(true);
	}

	public function testUpdateValueWithStringValue(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);

		$systemValueFacade = new SystemValueFacade(
			$systemValueRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
		);

		$now = new ImmutableDateTime('now');
		$enum = SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION;
		$stringValue = 'v1.2.3';

		$systemValueRepositoryMock
			->shouldReceive('findByEnum')
			->with($enum)
			->once()
			->andReturn(null);

		$datetimeFactoryMock
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock
			->shouldReceive('persist')
			->once();

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$systemValueFacade->updateValue($enum, null, null, $stringValue);

		$this->assertTrue(true);
	}

	public function testUpdateValueThrowsExceptionWhenNoValueProvided(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);

		$systemValueFacade = new SystemValueFacade(
			$systemValueRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
		);

		$enum = SystemValueEnum::DIVIDENDS_UPDATED_COUNT;

		$this->expectException(SystemValueInvalidArgumentException::class);
		$this->expectExceptionMessage('Exactly one value must be non-null');

		$systemValueFacade->updateValue($enum, null, null, null);
	}

	public function testUpdateValueThrowsExceptionWhenMultipleValuesProvided(): void
	{
		$systemValueRepositoryMock = Mockery::mock(SystemValueRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);

		$systemValueFacade = new SystemValueFacade(
			$systemValueRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
		);

		$enum = SystemValueEnum::DIVIDENDS_UPDATED_COUNT;
		$datetimeValue = new ImmutableDateTime('now');
		$intValue = 42;

		$this->expectException(SystemValueInvalidArgumentException::class);
		$this->expectExceptionMessage('Exactly one value must be non-null');

		$systemValueFacade->updateValue($enum, $datetimeValue, $intValue, null);
	}

}
