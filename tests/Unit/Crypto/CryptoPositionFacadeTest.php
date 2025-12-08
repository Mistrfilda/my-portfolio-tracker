<?php

declare(strict_types = 1);

namespace App\Test\Unit\Crypto;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Position\CryptoPositionFacade;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use App\UI\Icon\SvgIcon;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class CryptoPositionFacadeTest extends TestCase
{

	private CryptoPositionFacade $cryptoPositionFacade;

	private CryptoPositionRepository $cryptoPositionRepository;

	private CryptoAssetRepository $cryptoAssetRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	public function setUp(): void
	{
		$this->cryptoPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(CryptoPositionRepository::class);
		$this->cryptoAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(CryptoAssetRepository::class);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);
		$this->currentAppAdminGetter = UpdatedTestCase::createMockWithIgnoreMethods(CurrentAppAdminGetter::class);

		$this->cryptoPositionFacade = new CryptoPositionFacade(
			$this->cryptoPositionRepository,
			$this->cryptoAssetRepository,
			UpdatedTestCase::createMockWithIgnoreMethods(AssetPriceService::class),
			UpdatedTestCase::createMockWithIgnoreMethods(SummaryPriceService::class),
			UpdatedTestCase::createMockWithIgnoreMethods(CurrencyConversionFacade::class),
			$this->entityManager,
			$this->datetimeFactory,
			UpdatedTestCase::createMockWithIgnoreMethods(LoggerInterface::class),
			$this->currentAppAdminGetter,
		);
	}

	public function testCreatePosition(): void
	{
		$cryptoAssetId = Uuid::uuid4();
		$orderPiecesCount = 1.5;
		$pricePerPiece = 50000.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(75000.0, CurrencyEnum::USD);
		$differentBrokerAmount = false;
		$now = new ImmutableDateTime();

		$cryptoAsset = new CryptoAsset('Bitcoin', 'BTC', SvgIcon::CRYPTO_BITCOIN, $now);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();

		$this->cryptoAssetRepository->shouldReceive('getById')
			->with($cryptoAssetId)
			->once()
			->andReturn($cryptoAsset);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$result = $this->cryptoPositionFacade->create(
			$cryptoAssetId,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(CryptoPosition::class, $result);
	}

	public function testUpdatePosition(): void
	{
		$cryptoPositionId = Uuid::uuid4();
		$cryptoAssetId = Uuid::uuid4();
		$orderPiecesCount = 2.0;
		$pricePerPiece = 55000.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(110000.0, CurrencyEnum::USD);
		$differentBrokerAmount = false;
		$now = new ImmutableDateTime();

		$cryptoAsset = new CryptoAsset('Bitcoin', 'BTC', SvgIcon::CRYPTO_BITCOIN, $now);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$cryptoPosition = UpdatedTestCase::createMockWithIgnoreMethods(CryptoPosition::class);
		$adminUuid = Uuid::uuid4();
		$positionUuid = Uuid::uuid4();

		$this->cryptoAssetRepository->shouldReceive('getById')
			->with($cryptoAssetId)
			->once()
			->andReturn($cryptoAsset);

		$this->cryptoPositionRepository->shouldReceive('getById')
			->with($cryptoPositionId)
			->once()
			->andReturn($cryptoPosition);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$cryptoPosition->shouldReceive('update')
			->once();

		$cryptoPosition->shouldReceive('getId')
			->andReturn($positionUuid);

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$result = $this->cryptoPositionFacade->update(
			$cryptoPositionId,
			$cryptoAssetId,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(CryptoPosition::class, $result);
	}

	public function testIncludeToTotalValues(): void
	{
		$result = $this->cryptoPositionFacade->includeToTotalValues();

		$this->assertFalse($result);
	}

}
