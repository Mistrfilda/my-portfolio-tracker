<?php

declare(strict_types = 1);

namespace App\Test\Unit\Portu\Position;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\Portu\Asset\PortuAsset;
use App\Portu\Asset\PortuAssetRepository;
use App\Portu\Position\PortuPosition;
use App\Portu\Position\PortuPositionFacade;
use App\Portu\Position\PortuPositionRepository;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PortuPositionFacadeTest extends UpdatedTestCase
{

	public function testCreate(): void
	{
		$portuAssetRepositoryMock = Mockery::mock(PortuAssetRepository::class);
		$portuPositionRepositoryMock = Mockery::mock(PortuPositionRepository::class);
		$portuAssetPriceRecordRepositoryMock = Mockery::mock(PortuAssetPriceRecordRepository::class);
		$summaryPriceServiceMock = Mockery::mock(SummaryPriceService::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);
		$loggerMock->shouldIgnoreMissing();
		$currentAppAdminGetterMock = Mockery::mock(CurrentAppAdminGetter::class);
		$jobRequestFacadeMock = Mockery::mock(JobRequestFacade::class);

		$portuPositionFacade = new PortuPositionFacade(
			$portuAssetRepositoryMock,
			$portuPositionRepositoryMock,
			$portuAssetPriceRecordRepositoryMock,
			$summaryPriceServiceMock,
			$entityManagerMock,
			$datetimeFactoryMock,
			$loggerMock,
			$currentAppAdminGetterMock,
			$jobRequestFacadeMock,
		);

		$portuAssetId = Uuid::uuid4();
		$startDate = new ImmutableDateTime('2025-01-01 00:00:00');
		$startInvestmentPrice = 10000.0;
		$monthlyIncreasePrice = 1000.0;
		$currentValuePrice = 15000.0;
		$totalInvestedToThisDatePrice = 12000.0;
		$now = new ImmutableDateTime('2026-01-01 10:00:00');

		$portuAssetMock = new PortuAsset(
			'Test Asset',
			CurrencyEnum::CZK,
			new ImmutableDateTime('2025-01-01 00:00:00'),
		);

		$appAdminMock = Mockery::mock(AppAdmin::class);
		$appAdminMock->shouldReceive('getId')->andReturn(Uuid::uuid4());

		$portuAssetRepositoryMock
			->shouldReceive('getById')
			->once()
			->with($portuAssetId)
			->andReturn($portuAssetMock);

		$currentAppAdminGetterMock
			->shouldReceive('getAppAdmin')
			->twice()
			->andReturn($appAdminMock);

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

		$entityManagerMock
			->shouldReceive('refresh')
			->once();

		$portuPosition = $portuPositionFacade->create(
			$portuAssetId,
			$startDate,
			$startInvestmentPrice,
			$monthlyIncreasePrice,
			$currentValuePrice,
			$totalInvestedToThisDatePrice,
		);

		$this->assertEquals($portuAssetMock, $portuPosition->getPortuAsset());
		$this->assertEquals($startDate, $portuPosition->getStartDate());
		$this->assertEquals($startInvestmentPrice, $portuPosition->getStartInvestment()->getPrice());
		$this->assertEquals($monthlyIncreasePrice, $portuPosition->getMonthlyIncrease()->getPrice());
		$this->assertEquals($currentValuePrice, $portuPosition->getCurrentValue()->getPrice());
		$this->assertEquals(
			$totalInvestedToThisDatePrice,
			$portuPosition->getTotalInvestedToThisDate()->getPrice(),
		);
	}

	public function testUpdate(): void
	{
		$portuAssetRepositoryMock = Mockery::mock(PortuAssetRepository::class);
		$portuPositionRepositoryMock = Mockery::mock(PortuPositionRepository::class);
		$portuAssetPriceRecordRepositoryMock = Mockery::mock(PortuAssetPriceRecordRepository::class);
		$summaryPriceServiceMock = Mockery::mock(SummaryPriceService::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);
		$loggerMock->shouldIgnoreMissing();
		$currentAppAdminGetterMock = Mockery::mock(CurrentAppAdminGetter::class);
		$jobRequestFacadeMock = Mockery::mock(JobRequestFacade::class);

		$portuPositionFacade = new PortuPositionFacade(
			$portuAssetRepositoryMock,
			$portuPositionRepositoryMock,
			$portuAssetPriceRecordRepositoryMock,
			$summaryPriceServiceMock,
			$entityManagerMock,
			$datetimeFactoryMock,
			$loggerMock,
			$currentAppAdminGetterMock,
			$jobRequestFacadeMock,
		);

		$portuPositionId = Uuid::uuid4();
		$portuAssetId = Uuid::uuid4();
		$startDate = new ImmutableDateTime('2025-06-01 00:00:00');
		$startInvestmentPrice = 20000.0;
		$monthlyIncreasePrice = 2000.0;
		$currentValuePrice = 25000.0;
		$totalInvestedToThisDatePrice = 22000.0;
		$now = new ImmutableDateTime('2026-01-01 10:00:00');

		$portuAssetMock = new PortuAsset(
			'Test Asset Updated',
			CurrencyEnum::EUR,
			new ImmutableDateTime('2025-01-01 00:00:00'),
		);

		$appAdminMock = Mockery::mock(AppAdmin::class);
		$appAdminMock->shouldReceive('getId')->andReturn(Uuid::uuid4());

		$existingPosition = new PortuPosition(
			new PortuAsset('Old Asset', CurrencyEnum::CZK, new ImmutableDateTime('2025-01-01')),
			$appAdminMock,
			new ImmutableDateTime('2025-01-01'),
			new AssetPriceEmbeddable(10000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(11000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(10500.0, CurrencyEnum::CZK),
			new ImmutableDateTime('2025-01-01'),
		);

		$portuPositionRepositoryMock
			->shouldReceive('getById')
			->once()
			->with($portuPositionId)
			->andReturn($existingPosition);

		$portuAssetRepositoryMock
			->shouldReceive('getById')
			->once()
			->with($portuAssetId)
			->andReturn($portuAssetMock);

		$currentAppAdminGetterMock
			->shouldReceive('getAppAdmin')
			->twice()
			->andReturn($appAdminMock);

		$datetimeFactoryMock
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$entityManagerMock
			->shouldReceive('refresh')
			->once();

		$updatedPosition = $portuPositionFacade->update(
			$portuPositionId,
			$portuAssetId,
			$startDate,
			$startInvestmentPrice,
			$monthlyIncreasePrice,
			$currentValuePrice,
			$totalInvestedToThisDatePrice,
		);

		$this->assertEquals($portuAssetMock, $updatedPosition->getPortuAsset());
		$this->assertEquals($startDate, $updatedPosition->getStartDate());
		$this->assertEquals($startInvestmentPrice, $updatedPosition->getStartInvestment()->getPrice());
		$this->assertEquals($monthlyIncreasePrice, $updatedPosition->getMonthlyIncrease()->getPrice());
		$this->assertEquals($currentValuePrice, $updatedPosition->getCurrentValue()->getPrice());
	}

	public function testUpdatePriceForDateCreateNew(): void
	{
		$portuAssetRepositoryMock = Mockery::mock(PortuAssetRepository::class);
		$portuPositionRepositoryMock = Mockery::mock(PortuPositionRepository::class);
		$portuAssetPriceRecordRepositoryMock = Mockery::mock(PortuAssetPriceRecordRepository::class);
		$summaryPriceServiceMock = Mockery::mock(SummaryPriceService::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);
		$loggerMock->shouldIgnoreMissing();
		$currentAppAdminGetterMock = Mockery::mock(CurrentAppAdminGetter::class);
		$jobRequestFacadeMock = Mockery::mock(JobRequestFacade::class);
		$jobRequestFacadeMock->shouldIgnoreMissing();

		$portuPositionFacade = new PortuPositionFacade(
			$portuAssetRepositoryMock,
			$portuPositionRepositoryMock,
			$portuAssetPriceRecordRepositoryMock,
			$summaryPriceServiceMock,
			$entityManagerMock,
			$datetimeFactoryMock,
			$loggerMock,
			$currentAppAdminGetterMock,
			$jobRequestFacadeMock,
		);

		$portuPositionId = Uuid::uuid4();
		$date = new ImmutableDateTime('2025-12-01 00:00:00');
		$currentValuePrice = 18000.0;
		$totalInvestedToThisDatePrice = 16000.0;
		$now = new ImmutableDateTime('2026-01-01 10:00:00');

		$appAdminMock = Mockery::mock(AppAdmin::class);
		$portuAssetMock = new PortuAsset('Test Asset', CurrencyEnum::CZK, $now);
		$portuPositionMock = new PortuPosition(
			$portuAssetMock,
			$appAdminMock,
			new ImmutableDateTime('2025-01-01'),
			new AssetPriceEmbeddable(10000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(15000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(12000.0, CurrencyEnum::CZK),
			$now,
		);

		$portuPositionRepositoryMock
			->shouldReceive('getById')
			->once()
			->with($portuPositionId)
			->andReturn($portuPositionMock);

		$portuAssetPriceRecordRepositoryMock
			->shouldReceive('findByPositionAndDate')
			->once()
			->with($date, $portuPositionMock)
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

		$entityManagerMock
			->shouldReceive('refresh')
			->once();

		$priceRecord = $portuPositionFacade->updatePriceForDate(
			$portuPositionId,
			$date,
			$currentValuePrice,
			$totalInvestedToThisDatePrice,
			false,
		);

		$this->assertEquals($date, $priceRecord->getDate());
		$this->assertEquals($currentValuePrice, $priceRecord->getCurrentValue()->getPrice());
		$this->assertEquals(
			$totalInvestedToThisDatePrice,
			$priceRecord->getTotalInvestedAmount()->getPrice(),
		);
		$this->assertEquals($portuPositionMock, $priceRecord->getPortuPosition());
	}

	public function testUpdatePriceForDateUpdateExisting(): void
	{
		$portuAssetRepositoryMock = Mockery::mock(PortuAssetRepository::class);
		$portuPositionRepositoryMock = Mockery::mock(PortuPositionRepository::class);
		$portuAssetPriceRecordRepositoryMock = Mockery::mock(PortuAssetPriceRecordRepository::class);
		$summaryPriceServiceMock = Mockery::mock(SummaryPriceService::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);
		$loggerMock->shouldIgnoreMissing();
		$currentAppAdminGetterMock = Mockery::mock(CurrentAppAdminGetter::class);
		$jobRequestFacadeMock = Mockery::mock(JobRequestFacade::class);
		$jobRequestFacadeMock->shouldIgnoreMissing();

		$portuPositionFacade = new PortuPositionFacade(
			$portuAssetRepositoryMock,
			$portuPositionRepositoryMock,
			$portuAssetPriceRecordRepositoryMock,
			$summaryPriceServiceMock,
			$entityManagerMock,
			$datetimeFactoryMock,
			$loggerMock,
			$currentAppAdminGetterMock,
			$jobRequestFacadeMock,
		);

		$portuPositionId = Uuid::uuid4();
		$date = new ImmutableDateTime('2025-12-01 00:00:00');
		$currentValuePrice = 20000.0;
		$totalInvestedToThisDatePrice = 18000.0;
		$now = new ImmutableDateTime('2026-01-01 10:00:00');

		$appAdminMock = Mockery::mock(AppAdmin::class);
		$portuAssetMock = new PortuAsset('Test Asset', CurrencyEnum::CZK, $now);
		$portuPositionMock = new PortuPosition(
			$portuAssetMock,
			$appAdminMock,
			new ImmutableDateTime('2025-01-01'),
			new AssetPriceEmbeddable(10000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(1000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(15000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(12000.0, CurrencyEnum::CZK),
			$now,
		);

		$existingPriceRecord = new PortuAssetPriceRecord(
			$date,
			CurrencyEnum::CZK,
			new AssetPriceEmbeddable(15000.0, CurrencyEnum::CZK),
			new AssetPriceEmbeddable(13000.0, CurrencyEnum::CZK),
			$portuPositionMock,
			new ImmutableDateTime('2025-12-01'),
		);

		$portuPositionRepositoryMock
			->shouldReceive('getById')
			->once()
			->with($portuPositionId)
			->andReturn($portuPositionMock);

		$portuAssetPriceRecordRepositoryMock
			->shouldReceive('findByPositionAndDate')
			->once()
			->with($date, $portuPositionMock)
			->andReturn($existingPriceRecord);

		$datetimeFactoryMock
			->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$entityManagerMock
			->shouldReceive('refresh')
			->once();

		$priceRecord = $portuPositionFacade->updatePriceForDate(
			$portuPositionId,
			$date,
			$currentValuePrice,
			$totalInvestedToThisDatePrice,
			false,
		);

		$this->assertEquals($currentValuePrice, $priceRecord->getCurrentValue()->getPrice());
		$this->assertEquals(
			$totalInvestedToThisDatePrice,
			$priceRecord->getTotalInvestedAmount()->getPrice(),
		);
	}

}
