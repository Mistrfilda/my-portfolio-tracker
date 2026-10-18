<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset\Trend;

use App\Admin\AppAdmin;
use App\Asset\AssetRepository;
use App\Asset\Price\AssetPrice;
use App\Asset\Trend\AssetTrendFacade;
use App\Currency\CurrencyEnum;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationParameters;
use App\Notification\NotificationTypeEnum;
use App\Portu\Asset\PortuAsset;
use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

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

	public function testPortuValuesAreGroupedByOwnerAndExcludedFromSharedMarketReport(): void
	{
		$owners = [$this->createStub(AppAdmin::class), $this->createStub(AppAdmin::class)];
		foreach ($owners as $owner) {
			$owner->method('getId')->willReturn(Uuid::uuid4());
		}

		$assets = [];
		foreach (['A1', 'B1', 'A2'] as $index => $name) {
			$asset = $this->createStub(PortuAsset::class);
			$asset->method('getAppAdmin')->willReturn($owners[$index % 2]);
			$asset->method('getName')->willReturn($name);
			$asset->method('getTrend')->willReturn(10.0);
			$asset->method('getAssetCurrentPrice')->willReturn(
				new AssetPrice($asset, 1000 + $index, CurrencyEnum::CZK),
			);
			$assets[] = $asset;
		}

		$shared = $this->createStub(StockAsset::class);
		$shared->method('getName')->willReturn('Shared stock');
		$shared->method('getTrend')->willReturn(3.0);
		$shared->method('getAssetCurrentPrice')->willReturn(new AssetPrice($shared, 10, CurrencyEnum::USD));
		$assets[] = $shared;
		$this->assetRepositoryMock->method('getAllActiveAssets')->willReturn($assets);
		$this->datetimeFactoryMock->method('createNow')->willReturn(new ImmutableDateTime('2026-10-18'));
		$this->notificationFacadeMock->expects($this->once())->method('create')->with(
			NotificationTypeEnum::ASSET_TRENDS,
			[NotificationChannelEnum::DISCORD],
			'Asset trends',
			$this->isInstanceOf(NotificationParameters::class),
			['trends' => [['name' => 'Shared stock', 'currentPrice' => 10.0, 'currency' => 'USD', 'trend' => 3.0]]],
		);
		$personalReports = [];
		$this->notificationFacadeMock->expects($this->exactly(2))->method('createForRecipient')->willReturnCallback(
			static function (
				AppAdmin $recipient,
				NotificationTypeEnum $type,
				array $channels,
				string $message,
				NotificationParameters $parameters,
				array $data,
			) use (&$personalReports): void {
				self::assertSame(NotificationTypeEnum::ASSET_TRENDS, $type);
				self::assertSame([NotificationChannelEnum::DISCORD], $channels);
				self::assertSame('Portu trends', $message);
				self::assertSame(['trend_days_threshold' => 7], $parameters->getParameters());
				$personalReports[$recipient->getId()->toString()] = $data['trends'];
			},
		);
		$this->assetTrendFacade->processTrends(7);
		self::assertSame(['A1', 'A2'], array_column($personalReports[$owners[0]->getId()->toString()], 'name'));
		self::assertSame(
			[1000.0, 1002.0],
			array_column($personalReports[$owners[0]->getId()->toString()], 'currentPrice'),
		);
		self::assertSame(['B1'], array_column($personalReports[$owners[1]->getId()->toString()], 'name'));
		self::assertSame([1001.0], array_column($personalReports[$owners[1]->getId()->toString()], 'currentPrice'));
	}

	public function testPersonalReportIsCreatedWithoutAnySharedTrends(): void
	{
		$owner = $this->createStub(AppAdmin::class);
		$owner->method('getId')->willReturn(Uuid::uuid4());
		$asset = $this->createStub(PortuAsset::class);
		$asset->method('getAppAdmin')->willReturn($owner);
		$asset->method('getName')->willReturn('Personal portfolio');
		$asset->method('getTrend')->willReturn(-10.0);
		$asset->method('getAssetCurrentPrice')->willReturn(new AssetPrice($asset, 1000, CurrencyEnum::CZK));
		$this->assetRepositoryMock->method('getAllActiveAssets')->willReturn([$asset]);
		$this->datetimeFactoryMock->method('createNow')->willReturn(new ImmutableDateTime());
		$this->notificationFacadeMock->expects($this->never())->method('create');
		$this->notificationFacadeMock->expects($this->once())->method('createForRecipient')->with(
			$owner,
			NotificationTypeEnum::ASSET_TRENDS,
			[NotificationChannelEnum::DISCORD],
			'Portu trends',
			$this->isInstanceOf(NotificationParameters::class),
			['trends' => [['name' => 'Personal portfolio', 'currentPrice' => 1000.0, 'currency' => 'CZK', 'trend' => -10.0]]],
		);
		$this->assetTrendFacade->processTrends(7);
	}

}
