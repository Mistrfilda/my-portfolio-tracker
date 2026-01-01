<?php

declare(strict_types = 1);

namespace App\Test\Unit\Portu\Asset;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Portu\Asset\PortuAsset;
use App\Portu\Asset\PortuAssetFacade;
use App\Portu\Asset\PortuAssetRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PortuAssetFacadeTest extends UpdatedTestCase
{

	public function testCreate(): void
	{
		$portuAssetRepositoryMock = Mockery::mock(PortuAssetRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);
		$loggerMock->shouldIgnoreMissing();
		$currentAppAdminGetterMock = Mockery::mock(CurrentAppAdminGetter::class);

		$portuAssetFacade = new PortuAssetFacade(
			$portuAssetRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
			$loggerMock,
			$currentAppAdminGetterMock,
		);

		$name = 'Test Portu Asset';
		$currency = CurrencyEnum::CZK;
		$now = new ImmutableDateTime('2026-01-01 10:00:00');

		// setup mock expectations
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

		$appAdminMock = Mockery::mock(AppAdmin::class);
		$appAdminMock->shouldReceive('getName')->andReturn('Test Admin');

		$currentAppAdminGetterMock
			->shouldReceive('getAppAdmin')
			->once()
			->andReturn($appAdminMock);

		$portuAsset = $portuAssetFacade->create($name, $currency);

		$this->assertEquals($name, $portuAsset->getName());
		$this->assertEquals($currency, $portuAsset->getCurrency());
		$this->assertEquals($now, $portuAsset->getCreatedAt());
	}

	public function testUpdate(): void
	{
		$portuAssetRepositoryMock = Mockery::mock(PortuAssetRepository::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);
		$loggerMock->shouldIgnoreMissing();
		$currentAppAdminGetterMock = Mockery::mock(CurrentAppAdminGetter::class);

		$portuAssetFacade = new PortuAssetFacade(
			$portuAssetRepositoryMock,
			$entityManagerMock,
			$datetimeFactoryMock,
			$loggerMock,
			$currentAppAdminGetterMock,
		);

		$id = Uuid::uuid4();
		$name = 'Updated Portu Asset';
		$currency = CurrencyEnum::EUR;
		$now = new ImmutableDateTime('2026-01-01 10:00:00');

		$existingPortuAsset = new PortuAsset(
			'Old Name',
			CurrencyEnum::CZK,
			new ImmutableDateTime('2025-12-01 10:00:00'),
		);

		$portuAssetRepositoryMock
			->shouldReceive('getById')
			->once()
			->with($id)
			->andReturn($existingPortuAsset);

		$datetimeFactoryMock
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$appAdminMock = Mockery::mock(AppAdmin::class);
		$appAdminMock->shouldReceive('getName')->andReturn('Test Admin');

		$currentAppAdminGetterMock
			->shouldReceive('getAppAdmin')
			->once()
			->andReturn($appAdminMock);

		$updatedPortuAsset = $portuAssetFacade->update($id, $name, $currency);

		$this->assertEquals($name, $updatedPortuAsset->getName());
		$this->assertEquals($currency, $updatedPortuAsset->getCurrency());
		$this->assertEquals($now, $updatedPortuAsset->getUpdatedAt());
	}

}
