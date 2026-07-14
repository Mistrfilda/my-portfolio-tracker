<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset\Trend;

use App\Asset\AssetRepository;
use App\Asset\Price\AssetPrice;
use App\Asset\Trend\AssetTrendFacade;
use App\Currency\CurrencyEnum;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationParameters;
use App\Notification\NotificationTypeEnum;
use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
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

	public function testProcessTrendsCreatesOneNotificationWithAllThresholdExceedingTrends(): void
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
		$activeAssetMock
			->expects($this->once())
			->method('getTrend')
			->with($deductedDate)
			->willReturn(3.0);
		$activeAssetMock->method('getName')->willReturn('Test Asset');
		$activeAssetMock->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice(
				$activeAssetMock,
				100,
				CurrencyEnum::CZK,
			),
		);

		$secondActiveAssetMock = $this->createMock(StockAsset::class);
		$secondActiveAssetMock
			->expects($this->once())
			->method('getTrend')
			->with($deductedDate)
			->willReturn(-4.5);
		$secondActiveAssetMock->method('getName')->willReturn('Second Asset');
		$secondActiveAssetMock->method('getAssetCurrentPrice')->willReturn(
			new AssetPrice(
				$secondActiveAssetMock,
				25.5,
				CurrencyEnum::USD,
			),
		);

		$this->assetRepositoryMock
			->method('getAllActiveAssets')
			->willReturn([$activeAssetMock, $secondActiveAssetMock]);

		$this->notificationFacadeMock
			->expects($this->once())
			->method('create')
			->with(
				NotificationTypeEnum::ASSET_TRENDS,
				[NotificationChannelEnum::DISCORD],
				'Asset trends',
				$this->callback(static function (NotificationParameters $parameters): bool {
					self::assertSame(
						[NotificationParameterEnum::TREND_DAYS_THRESHOLD->value => 7],
						$parameters->getParameters(),
					);

					return true;
				}),
				[
					'trends' => [
						[
							'name' => 'Test Asset',
							'currentPrice' => 100.0,
							'currency' => CurrencyEnum::CZK->value,
							'trend' => 3.0,
						],
						[
							'name' => 'Second Asset',
							'currentPrice' => 25.5,
							'currency' => CurrencyEnum::USD->value,
							'trend' => -4.5,
						],
					],
				],
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
		$activeAssetMock
			->expects($this->once())
			->method('getTrend')
			->with($deductedDate)
			->willReturn(1.0);

		$this->assetRepositoryMock
			->method('getAllActiveAssets')
			->willReturn([$activeAssetMock]);

		$this->notificationFacadeMock
			->expects($this->never())
			->method('create');

		$this->assetTrendFacade->processTrends(7, 2);
	}

}
