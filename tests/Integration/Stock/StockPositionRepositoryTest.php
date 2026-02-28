<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Admin\AppAdmin;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class StockPositionRepositoryTest extends IntegrationTestCase
{

	private StockPositionRepository $stockPositionRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->stockPositionRepository = $this->getService(StockPositionRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testGetById(): void
	{
		$stockAsset = $this->createStockAsset('Test Stock', 'POS-TEST-1');
		$appAdmin = $this->createAppAdmin('pos-test-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->persist($appAdmin);

		$position = $this->createStockPosition($appAdmin, $stockAsset, 10, 150.0);
		$this->entityManager->persist($position);
		$this->entityManager->flush();

		$result = $this->stockPositionRepository->getById($position->getId());

		$this->assertSame($position->getId()->toString(), $result->getId()->toString());
		$this->assertSame(10, $result->getOrderPiecesCount());
	}

	public function testGetByIdThrowsExceptionWhenNotFound(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->stockPositionRepository->getById(Uuid::uuid4());
	}

	public function testFindAll(): void
	{
		$stockAsset = $this->createStockAsset('Find All Stock', 'POS-FALL-1');
		$appAdmin = $this->createAppAdmin('pos-fall-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->persist($appAdmin);

		$position1 = $this->createStockPosition($appAdmin, $stockAsset, 5, 100.0);
		$position2 = $this->createStockPosition($appAdmin, $stockAsset, 10, 200.0);
		$this->entityManager->persist($position1);
		$this->entityManager->persist($position2);
		$this->entityManager->flush();

		$result = $this->stockPositionRepository->findAll();

		$ids = array_map(
			static fn (StockPosition $position): string => $position->getId()->toString(),
			$result,
		);

		$this->assertContains($position1->getId()->toString(), $ids);
		$this->assertContains($position2->getId()->toString(), $ids);
	}

	public function testFindAllOpenedDoesNotContainClosedPositions(): void
	{
		$stockAsset = $this->createStockAsset('Opened Stock', 'POS-OPEN-1');
		$appAdmin = $this->createAppAdmin('pos-open-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->persist($appAdmin);

		$openPosition = $this->createStockPosition($appAdmin, $stockAsset, 10, 150.0);
		$this->entityManager->persist($openPosition);

		$closedStockPosition = $this->createStockPosition($appAdmin, $stockAsset, 5, 120.0);
		$closedPosition = new StockClosedPosition(
			$closedStockPosition,
			130.0,
			new ImmutableDateTime(),
			false,
			new AssetPriceEmbeddable(650.0, CurrencyEnum::USD),
			new ImmutableDateTime(),
		);
		$closedStockPosition->closePosition($closedPosition);
		$this->entityManager->persist($closedStockPosition);
		$this->entityManager->persist($closedPosition);
		$this->entityManager->flush();

		$this->entityManager->clear();

		$result = $this->stockPositionRepository->findAllOpened();

		$ids = array_map(
			static fn (StockPosition $position): string => $position->getId()->toString(),
			$result,
		);

		$this->assertContains($openPosition->getId()->toString(), $ids);
		$this->assertNotContains($closedStockPosition->getId()->toString(), $ids);
	}

	public function testFindAllOpenedInCurrency(): void
	{
		$usdAsset = $this->createStockAsset('USD Stock', 'POS-USD-1', CurrencyEnum::USD);
		$eurAsset = $this->createStockAsset('EUR Stock', 'POS-EUR-1', CurrencyEnum::EUR);
		$appAdmin = $this->createAppAdmin('pos-curr-1');
		$this->entityManager->persist($usdAsset);
		$this->entityManager->persist($eurAsset);
		$this->entityManager->persist($appAdmin);

		$usdPosition = $this->createStockPosition($appAdmin, $usdAsset, 5, 100.0);
		$eurPosition = $this->createStockPosition($appAdmin, $eurAsset, 3, 80.0);
		$this->entityManager->persist($usdPosition);
		$this->entityManager->persist($eurPosition);
		$this->entityManager->flush();

		$result = $this->stockPositionRepository->findAllOpenedInCurrency(CurrencyEnum::USD);

		$ids = array_map(
			static fn (StockPosition $position): string => $position->getId()->toString(),
			$result,
		);

		$this->assertContains($usdPosition->getId()->toString(), $ids);
		$this->assertNotContains($eurPosition->getId()->toString(), $ids);
	}

	public function testFindByIds(): void
	{
		$stockAsset = $this->createStockAsset('ByIds Stock', 'POS-IDS-1');
		$appAdmin = $this->createAppAdmin('pos-ids-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->persist($appAdmin);

		$position1 = $this->createStockPosition($appAdmin, $stockAsset, 5, 100.0);
		$position2 = $this->createStockPosition($appAdmin, $stockAsset, 10, 200.0);
		$this->entityManager->persist($position1);
		$this->entityManager->persist($position2);
		$this->entityManager->flush();

		$result = $this->stockPositionRepository->findByIds([$position1->getId()->toString()]);

		$this->assertCount(1, $result);
		$this->assertSame($position1->getId()->toString(), $result[0]->getId()->toString());
	}

	public function testFindByIdsReturnsEmptyArrayForEmptyInput(): void
	{
		$result = $this->stockPositionRepository->findByIds([]);
		$this->assertCount(0, $result);
	}

	private function createStockAsset(
		string $name,
		string $ticker,
		CurrencyEnum $currency = CurrencyEnum::USD,
	): StockAsset
	{
		return new StockAsset(
			$name,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NYSE,
			$currency,
			new ImmutableDateTime(),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
	}

	private function createAppAdmin(string $suffix): AppAdmin
	{
		return new AppAdmin(
			'Test Admin ' . $suffix,
			'testadmin_' . $suffix,
			'testadmin_' . $suffix . '@test.com',
			'password123',
			new ImmutableDateTime(),
			false,
			false,
		);
	}

	private function createStockPosition(
		AppAdmin $appAdmin,
		StockAsset $stockAsset,
		int $pieces,
		float $pricePerPiece,
	): StockPosition
	{
		return new StockPosition(
			$appAdmin,
			$stockAsset,
			$pieces,
			$pricePerPiece,
			new ImmutableDateTime(),
			new AssetPriceEmbeddable($pieces * $pricePerPiece, $stockAsset->getCurrency()),
			false,
			new ImmutableDateTime(),
		);
	}

}
