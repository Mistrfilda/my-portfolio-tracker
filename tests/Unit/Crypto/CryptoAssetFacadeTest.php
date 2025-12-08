<?php

declare(strict_types = 1);

namespace App\Test\Unit\Crypto;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetFacade;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Asset\CryptoAssetTickerAlreadyExistsException;
use App\Test\UpdatedTestCase;
use App\UI\Icon\SvgIcon;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class CryptoAssetFacadeTest extends TestCase
{

	private CryptoAssetFacade $cryptoAssetFacade;

	private CryptoAssetRepository $cryptoAssetRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	public function setUp(): void
	{
		$this->cryptoAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(CryptoAssetRepository::class);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);
		$this->currentAppAdminGetter = UpdatedTestCase::createMockWithIgnoreMethods(CurrentAppAdminGetter::class);

		$this->cryptoAssetFacade = new CryptoAssetFacade(
			$this->cryptoAssetRepository,
			$this->entityManager,
			$this->datetimeFactory,
			UpdatedTestCase::createMockWithIgnoreMethods(LoggerInterface::class),
			$this->currentAppAdminGetter,
		);
	}

	public function testCreateCryptoAsset(): void
	{
		$name = 'Bitcoin';
		$ticker = 'BTC';
		$now = new ImmutableDateTime();

		$this->cryptoAssetRepository->shouldReceive('findByTicker')
			->with($ticker)
			->once()
			->andReturn(null);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$appAdmin->shouldReceive('getName')
			->andReturn('Test Admin');

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$result = $this->cryptoAssetFacade->create($name, $ticker, SvgIcon::CRYPTO_BITCOIN);

		$this->assertInstanceOf(CryptoAsset::class, $result);
		$this->assertSame($name, $result->getName());
		$this->assertSame($ticker, $result->getTicker());
	}

	public function testCreateThrowsExceptionWhenTickerAlreadyExists(): void
	{
		$name = 'Bitcoin';
		$ticker = 'BTC';
		$existingAsset = UpdatedTestCase::createMockWithIgnoreMethods(CryptoAsset::class);

		$this->cryptoAssetRepository->shouldReceive('findByTicker')
			->with($ticker)
			->once()
			->andReturn($existingAsset);

		$this->expectException(CryptoAssetTickerAlreadyExistsException::class);

		$this->cryptoAssetFacade->create($name, $ticker, SvgIcon::CRYPTO_BITCOIN);
	}

	public function testUpdateCryptoAsset(): void
	{
		$id = Uuid::uuid4();
		$name = 'Bitcoin Updated';
		$ticker = 'BTC';
		$now = new ImmutableDateTime();

		$cryptoAsset = new CryptoAsset('Bitcoin', 'BTC', SvgIcon::CRYPTO_BITCOIN, $now);

		$this->cryptoAssetRepository->shouldReceive('getById')
			->with($id)
			->once()
			->andReturn($cryptoAsset);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$appAdmin = UpdatedTestCase::createMockWithIgnoreMethods(AppAdmin::class);
		$appAdmin->shouldReceive('getName')
			->andReturn('Test Admin');

		$this->currentAppAdminGetter->shouldReceive('getAppAdmin')
			->andReturn($appAdmin);

		$this->entityManager->shouldReceive('flush')
			->once();

		$result = $this->cryptoAssetFacade->update($id, $name, $ticker, SvgIcon::CRYPTO_BITCOIN);

		$this->assertInstanceOf(CryptoAsset::class, $result);
		$this->assertSame($name, $result->getName());
		$this->assertSame($ticker, $result->getTicker());
	}

}
