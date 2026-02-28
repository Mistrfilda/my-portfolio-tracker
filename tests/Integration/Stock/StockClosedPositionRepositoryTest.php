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
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class StockClosedPositionRepositoryTest extends IntegrationTestCase
{

	private StockClosedPositionRepository $closedPositionRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->closedPositionRepository = $this->getService(StockClosedPositionRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testGetById(): void
	{
		$closedPosition = $this->createClosedPositionWithDependencies(
			'CP-TEST-1',
			'cp-test-1',
			155.0,
			'2025-06-15',
		);

		$result = $this->closedPositionRepository->getById($closedPosition->getId());

		$this->assertSame($closedPosition->getId()->toString(), $result->getId()->toString());
	}

	public function testGetByIdThrowsExceptionWhenNotFound(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->closedPositionRepository->getById(Uuid::uuid4());
	}

	public function testFindAll(): void
	{
		$closedPosition1 = $this->createClosedPositionWithDependencies(
			'CP-FA-1',
			'cp-fa-1',
			160.0,
			'2025-03-15',
		);

		$closedPosition2 = $this->createClosedPositionWithDependencies(
			'CP-FA-2',
			'cp-fa-2',
			170.0,
			'2025-04-15',
		);

		$result = $this->closedPositionRepository->findAll();

		$ids = array_map(
			static fn (StockClosedPosition $cp): string => $cp->getId()->toString(),
			$result,
		);

		$this->assertContains($closedPosition1->getId()->toString(), $ids);
		$this->assertContains($closedPosition2->getId()->toString(), $ids);
	}

	public function testFindBetweenDates(): void
	{
		$earlyPosition = $this->createClosedPositionWithDependencies(
			'CP-BD-1',
			'cp-bd-1',
			100.0,
			'2024-01-15',
		);

		$midPosition = $this->createClosedPositionWithDependencies(
			'CP-BD-2',
			'cp-bd-2',
			120.0,
			'2025-06-15',
		);

		$latePosition = $this->createClosedPositionWithDependencies(
			'CP-BD-3',
			'cp-bd-3',
			140.0,
			'2026-12-15',
		);

		$result = $this->closedPositionRepository->findBetweenDates(
			new ImmutableDateTime('2025-01-01'),
			new ImmutableDateTime('2025-12-31'),
		);

		$ids = array_map(
			static fn (StockClosedPosition $cp): string => $cp->getId()->toString(),
			$result,
		);

		$this->assertContains($midPosition->getId()->toString(), $ids);
		$this->assertNotContains($earlyPosition->getId()->toString(), $ids);
		$this->assertNotContains($latePosition->getId()->toString(), $ids);
	}

	public function testFindBetweenDatesReturnsEmptyForNoMatches(): void
	{
		$result = $this->closedPositionRepository->findBetweenDates(
			new ImmutableDateTime('2098-01-01'),
			new ImmutableDateTime('2098-12-31'),
		);

		$this->assertCount(0, $result);
	}

	public function testFindByIds(): void
	{
		$closedPosition1 = $this->createClosedPositionWithDependencies(
			'CP-IDS-1',
			'cp-ids-1',
			180.0,
			'2025-05-10',
		);

		$this->createClosedPositionWithDependencies(
			'CP-IDS-2',
			'cp-ids-2',
			190.0,
			'2025-05-20',
		);

		$result = $this->closedPositionRepository->findByIds([$closedPosition1->getId()->toString()]);

		$this->assertCount(1, $result);
		$this->assertSame($closedPosition1->getId()->toString(), $result[0]->getId()->toString());
	}

	public function testFindByIdsReturnsEmptyArrayForEmptyInput(): void
	{
		$result = $this->closedPositionRepository->findByIds([]);
		$this->assertCount(0, $result);
	}

	public function testFindBetweenDatesOrderedByDate(): void
	{
		$this->createClosedPositionWithDependencies(
			'CP-ORD-1',
			'cp-ord-1',
			200.0,
			'2025-09-20',
		);

		$this->createClosedPositionWithDependencies(
			'CP-ORD-2',
			'cp-ord-2',
			180.0,
			'2025-09-05',
		);

		$result = $this->closedPositionRepository->findBetweenDates(
			new ImmutableDateTime('2025-09-01'),
			new ImmutableDateTime('2025-09-30'),
		);

		$this->assertGreaterThanOrEqual(2, count($result));

		$dates = array_map(
			static fn (StockClosedPosition $cp): string => $cp->getDate()->format('Y-m-d'),
			$result,
		);

		$sortedDates = $dates;
		sort($sortedDates);
		$this->assertSame($sortedDates, $dates);
	}

	private function createClosedPositionWithDependencies(
		string $ticker,
		string $adminSuffix,
		float $closePricePerPiece,
		string $closeDate,
	): StockClosedPosition
	{
		$stockAsset = new StockAsset(
			'Stock ' . $ticker,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
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

		$appAdmin = new AppAdmin(
			'Test Admin ' . $adminSuffix,
			'testadmin_' . $adminSuffix,
			'testadmin_' . $adminSuffix . '@test.com',
			'password123',
			new ImmutableDateTime(),
			false,
			false,
		);

		$this->entityManager->persist($stockAsset);
		$this->entityManager->persist($appAdmin);

		$position = new StockPosition(
			$appAdmin,
			$stockAsset,
			10,
			100.0,
			new ImmutableDateTime('2024-01-01'),
			new AssetPriceEmbeddable(1000.0, CurrencyEnum::USD),
			false,
			new ImmutableDateTime(),
		);

		$closedPosition = new StockClosedPosition(
			$position,
			$closePricePerPiece,
			new ImmutableDateTime($closeDate),
			false,
			new AssetPriceEmbeddable($closePricePerPiece * 10, CurrencyEnum::USD),
			new ImmutableDateTime(),
		);

		$position->closePosition($closedPosition);

		$this->entityManager->persist($position);
		$this->entityManager->persist($closedPosition);
		$this->entityManager->flush();

		return $closedPosition;
	}

}
