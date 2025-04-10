<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset\Trend;

use App\Asset\AssetRepository;
use App\Asset\Price\AssetPrice;
use App\Asset\Trend\AssetTrendFacade;
use App\Currency\CurrencyEnum;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationTypeEnum;
use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class AssetTrendFacadeTest extends TestCase
{

	private AssetTrendFacade $assetTrendFacade;

	private NotificationFacade $notificationFacadeMock;

	private DatetimeFactory $datetimeFactoryMock;

	private AssetRepository $assetRepositoryMock;

	protected function setUp(): void
	{
		$this->notificationFacadeMock = $this->createMock(NotificationFacade::class);
		$this->datetimeFactoryMock = $this->createMock(DatetimeFactory::class);
		$this->assetRepositoryMock = $this->createMock(AssetRepository::class);

		$this->assetTrendFacade = new AssetTrendFacade(
			[$this->assetRepositoryMock],
			$this->datetimeFactoryMock,
			$this->notificationFacadeMock,
		);
	}

	public function testProcessTrendsWithPositiveThresholdExceedingTrend(): void
	{
		$dateTimeMock = $this->createMock(ImmutableDateTime::class);
		$this->datetimeFactoryMock
			->method('createNow')
			->willReturn($dateTimeMock);

		$deductedDate = $this->createMock(ImmutableDateTime::class);
		$dateTimeMock
			->expects($this->once())
			->method('deductDaysFromDatetime')
			->with(7)
			->willReturn($deductedDate);

		$activeAssetMock = $this->createMock(StockAsset::class);
		$activeAssetMock->method('getTrend')->with($deductedDate)->willReturn(3.0);
		$activeAssetMock->method('getName')->willReturn('Test Asset');
		$activeAssetMock->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice(
				$activeAssetMock,
				100,
				CurrencyEnum::CZK,
			),
		);

		$this->assetRepositoryMock
			->method('getAllActiveAssets')
			->willReturn([$activeAssetMock]);

		$this->notificationFacadeMock
			->expects($this->once())
			->method('create')
			->with(
				NotificationTypeEnum::PRICE_ALERT_UP,
				[NotificationChannelEnum::DISCORD],
				$this->stringContains('3.00 %'),
			);

		$this->assetTrendFacade->processTrends(7, 2);
	}

	public function testProcessTrendsWithNegativeThresholdExceedingTrend(): void
	{
		$dateTimeMock = $this->createMock(ImmutableDateTime::class);
		$this->datetimeFactoryMock
			->method('createNow')
			->willReturn($dateTimeMock);

		$deductedDate = $this->createMock(ImmutableDateTime::class);
		$dateTimeMock
			->expects($this->once())
			->method('deductDaysFromDatetime')
			->with(7)
			->willReturn($deductedDate);

		$activeAssetMock = $this->createMock(StockAsset::class);
		$activeAssetMock->method('getTrend')->with($deductedDate)->willReturn(-3.0);
		$activeAssetMock->method('getName')->willReturn('Test Asset');
		$activeAssetMock->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice(
				$activeAssetMock,
				100,
				CurrencyEnum::CZK,
			),
		);

		$this->assetRepositoryMock
			->method('getAllActiveAssets')
			->willReturn([$activeAssetMock]);

		$this->notificationFacadeMock
			->expects($this->once())
			->method('create')
			->with(
				NotificationTypeEnum::PRICE_ALERT_DOWN,
				[NotificationChannelEnum::DISCORD],
				$this->stringContains('-3.00 %'),
			);

		$this->assetTrendFacade->processTrends(7, 2);
	}

	public function testProcessTrendsWithNoThresholdExceedingTrend(): void
	{
		$dateTimeMock = $this->createMock(ImmutableDateTime::class);
		$this->datetimeFactoryMock
			->method('createNow')
			->willReturn($dateTimeMock);

		$deductedDate = $this->createMock(ImmutableDateTime::class);
		$dateTimeMock
			->expects($this->once())
			->method('deductDaysFromDatetime')
			->with(7)
			->willReturn($deductedDate);

		$activeAssetMock = $this->createMock(StockAsset::class);
		$activeAssetMock->method('getTrend')->with($deductedDate)->willReturn(1.0);

		$this->assetRepositoryMock
			->method('getAllActiveAssets')
			->willReturn([$activeAssetMock]);

		$this->notificationFacadeMock
			->expects($this->never())
			->method('create');

		$this->assetTrendFacade->processTrends(7, 2);
	}

}
