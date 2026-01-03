<?php

declare(strict_types = 1);

namespace App\Test\Unit\System;

use App\System\Exception\SystemValueInvalidArgumentException;
use App\System\SystemValue;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\System\SystemValueRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class SystemValueFacadeTest extends UpdatedTestCase
{

	private SystemValueFacade $systemValueFacade;

	private SystemValueRepository $systemValueRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	protected function setUp(): void
	{
		$this->systemValueRepository = Mockery::mock(SystemValueRepository::class);
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);

		$this->systemValueFacade = new SystemValueFacade(
			$this->systemValueRepository,
			$this->entityManager,
			$this->datetimeFactory,
		);
	}

	public function testUpdateValueCreatesNewValueWhenNotExists(): void
	{
		$now = new ImmutableDateTime('2024-01-15');
		$datetimeValue = new ImmutableDateTime('2024-01-20');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->systemValueRepository
			->shouldReceive('findByEnum')
			->once()
			->with(SystemValueEnum::DIVIDENDS_UPDATED_AT)
			->andReturn(null);

		$this->entityManager
			->shouldReceive('persist')
			->once()
			->with(
				Mockery::on(
					static fn (SystemValue $systemValue): bool => $systemValue->getSystemValueEnum() === SystemValueEnum::DIVIDENDS_UPDATED_AT
							&& $systemValue->getDatetimeValue() === $datetimeValue
							&& $systemValue->getIntValue() === null
							&& $systemValue->getStringValue() === null,
				),
			);

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->assertNoError(fn () => $this->systemValueFacade->updateValue(
			SystemValueEnum::DIVIDENDS_UPDATED_AT,
			datetimeValue: $datetimeValue,
		));
	}

	public function testUpdateValueUpdatesExistingValue(): void
	{
		$now = new ImmutableDateTime('2024-01-15');
		$existingValue = new SystemValue(
			SystemValueEnum::DIVIDENDS_UPDATED_COUNT,
			null,
			5,
			null,
			new ImmutableDateTime('2024-01-10'),
		);

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->systemValueRepository
			->shouldReceive('findByEnum')
			->once()
			->with(SystemValueEnum::DIVIDENDS_UPDATED_COUNT)
			->andReturn($existingValue);

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->systemValueFacade->updateValue(
			SystemValueEnum::DIVIDENDS_UPDATED_COUNT,
			intValue: 10,
		);

		$this->assertSame(10, $existingValue->getIntValue());
		$this->assertSame($now, $existingValue->getUpdatedAt());
	}

	public function testUpdateValueWithStringValue(): void
	{
		$now = new ImmutableDateTime('2024-01-15');

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->systemValueRepository
			->shouldReceive('findByEnum')
			->once()
			->andReturn(null);

		$this->entityManager
			->shouldReceive('persist')
			->once()
			->with(
				Mockery::on(static fn (SystemValue $systemValue): bool => $systemValue->getStringValue() === 'v1.0.0'),
			);

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->assertNoError(fn () => $this->systemValueFacade->updateValue(
			SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION,
			stringValue: 'v1.0.0',
		));
	}

	public function testUpdateValueThrowsExceptionWhenNoValueProvided(): void
	{
		self::assertException(
			fn () => $this->systemValueFacade->updateValue(SystemValueEnum::DIVIDENDS_UPDATED_AT),
			SystemValueInvalidArgumentException::class,
			'Exactly one value must be non-null',
		);
	}

	public function testUpdateValueThrowsExceptionWhenMultipleValuesProvided(): void
	{
		self::assertException(
			fn () => $this->systemValueFacade->updateValue(
				SystemValueEnum::DIVIDENDS_UPDATED_AT,
				datetimeValue: new ImmutableDateTime(),
				intValue: 5,
			),
			SystemValueInvalidArgumentException::class,
			'Exactly one value must be non-null',
		);
	}

	public function testUpdateValueThrowsExceptionWhenAllValuesProvided(): void
	{
		self::assertException(
			fn () => $this->systemValueFacade->updateValue(
				SystemValueEnum::DIVIDENDS_UPDATED_AT,
				datetimeValue: new ImmutableDateTime(),
				intValue: 5,
				stringValue: 'test',
			),
			SystemValueInvalidArgumentException::class,
			'Exactly one value must be non-null',
		);
	}

}
