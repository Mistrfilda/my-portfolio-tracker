<?php

declare(strict_types = 1);

namespace App\Test\Unit\Crypto;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\Closed\CryptoClosedPosition;
use App\Crypto\Position\Closed\CryptoClosedPositionFacade;
use App\Crypto\Position\Closed\CryptoClosedPositionRepository;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class CryptoClosedPositionFacadeTest extends TestCase
{

	private CryptoClosedPositionFacade $cryptoClosedPositionFacade;

	private CryptoPositionRepository $cryptoPositionRepository;

	private CryptoClosedPositionRepository $cryptoClosedPositionRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	public function setUp(): void
	{
		$this->cryptoPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(CryptoPositionRepository::class);
		$this->cryptoClosedPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			CryptoClosedPositionRepository::class,
		);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);
		$this->currentAppAdminGetter = UpdatedTestCase::createMockWithIgnoreMethods(CurrentAppAdminGetter::class);

		$this->cryptoClosedPositionFacade = new CryptoClosedPositionFacade(
			$this->cryptoPositionRepository,
			$this->cryptoClosedPositionRepository,
			UpdatedTestCase::createMockWithIgnoreMethods(CryptoAssetRepository::class),
			$this->entityManager,
			$this->datetimeFactory,
			UpdatedTestCase::createMockWithIgnoreMethods(LoggerInterface::class),
			$this->currentAppAdminGetter,
			UpdatedTestCase::createMockWithIgnoreMethods(CurrencyConversionFacade::class),
			UpdatedTestCase::createMockWithIgnoreMethods(SummaryPriceService::class),
		);
	}

	public function testCreateClosedPosition(): void
	{
		$cryptoPositionId = Uuid::uuid4();
		$pricePerPiece = 60000.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(60000.0, CurrencyEnum::USD);
		$differentBrokerAmount = false;
		$now = new ImmutableDateTime();

		$cryptoPosition = UpdatedTestCase::createMockWithIgnoreMethods(CryptoPosition::class);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();

		$this->cryptoPositionRepository->shouldReceive('getById')
			->with($cryptoPositionId)
			->once()
			->andReturn($cryptoPosition);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->entityManager->shouldReceive('persist')
			->once();

		$cryptoPosition->shouldReceive('closePosition')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$result = $this->cryptoClosedPositionFacade->create(
			$cryptoPositionId,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(CryptoClosedPosition::class, $result);
	}

	public function testUpdateClosedPosition(): void
	{
		$cryptoClosedPositionId = Uuid::uuid4();
		$pricePerPiece = 65000.0;
		$orderDate = new ImmutableDateTime();
		$totalInvestedAmount = new AssetPriceEmbeddable(65000.0, CurrencyEnum::USD);
		$differentBrokerAmount = false;
		$now = new ImmutableDateTime();

		$cryptoClosedPosition = UpdatedTestCase::createMockWithIgnoreMethods(CryptoClosedPosition::class);
		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$adminUuid = Uuid::uuid4();
		$positionUuid = Uuid::uuid4();

		$this->cryptoClosedPositionRepository->shouldReceive('getById')
			->with($cryptoClosedPositionId)
			->once()
			->andReturn($cryptoClosedPosition);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$appAdmin->shouldReceive('getId')
			->andReturn($adminUuid);

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$cryptoClosedPosition->shouldReceive('update')
			->once();

		$cryptoClosedPosition->shouldReceive('getId')
			->andReturn($positionUuid);

		$this->entityManager->shouldReceive('flush')
			->once();

		$this->entityManager->shouldReceive('refresh')
			->once();

		$result = $this->cryptoClosedPositionFacade->update(
			$cryptoClosedPositionId,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmount,
			$differentBrokerAmount,
		);

		$this->assertInstanceOf(CryptoClosedPosition::class, $result);
	}

}
