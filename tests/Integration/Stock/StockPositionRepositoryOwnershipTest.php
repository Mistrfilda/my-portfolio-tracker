<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class StockPositionRepositoryOwnershipTest extends IntegrationTestCase
{

	private StockPositionRepository $repository;

	private EntityManagerInterface $entityManager;

	private CurrentAppAdminGetter $currentAppAdminGetter;

	private AppAdmin $firstAdmin;

	private AppAdmin $secondAdmin;

	private StockPosition $firstPosition;

	private StockPosition $secondPosition;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = $this->getService(StockPositionRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
		$this->currentAppAdminGetter = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAppAdminGetter->beginApiRequest();

		$this->firstAdmin = $this->createAppAdmin(true);
		$this->secondAdmin = $this->createAppAdmin(false);
		$asset = new StockAsset(
			'Ownership test stock',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'OWN-' . bin2hex(random_bytes(4)),
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			$this->createDatetime(),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: false,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
		$this->entityManager->persist($asset);

		$this->firstPosition = $this->createPosition($this->firstAdmin, $asset, 10);
		$this->secondPosition = $this->createPosition($this->secondAdmin, $asset, 20);
		$this->entityManager->flush();
		$this->entityManager->clear();
	}

	protected function tearDown(): void
	{
		$this->currentAppAdminGetter->endApiRequest();

		parent::tearDown();
	}

	public function testListsOnlyRequestedOwnersPositionsWithoutLogin(): void
	{
		self::assertFalse($this->currentAppAdminGetter->isLoggedIn());
		self::assertSame(
			[$this->firstPosition->getId()->toString()],
			$this->positionIds($this->repository->findAllForAppAdmin($this->firstAdmin)),
		);
		self::assertSame(
			[$this->secondPosition->getId()->toString()],
			$this->positionIds($this->repository->findAllForAppAdmin($this->secondAdmin)),
		);
	}

	public function testLoadsPositionByIdForItsOwner(): void
	{
		$position = $this->repository->getByIdForAppAdmin($this->firstPosition->getId(), $this->firstAdmin);

		self::assertSame($this->firstPosition->getId()->toString(), $position->getId()->toString());
		self::assertSame(10, $position->getOrderPiecesCount());
	}

	public function testRejectsOtherOwnersPositionEvenForSysAdmin(): void
	{
		self::assertTrue($this->firstAdmin->isSysAdmin());
		$this->expectException(NoEntityFoundException::class);

		$this->repository->getByIdForAppAdmin($this->secondPosition->getId(), $this->firstAdmin);
	}

	public function testRejectsMissingPosition(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->repository->getByIdForAppAdmin(Uuid::uuid4(), $this->firstAdmin);
	}

	public function testExplicitOwnerDoesNotDependOnCurrentUser(): void
	{
		$this->currentAppAdminGetter->setApiAppAdmin($this->firstAdmin);

		self::assertSame(
			[$this->secondPosition->getId()->toString()],
			$this->positionIds($this->repository->findAllForAppAdmin($this->secondAdmin)),
		);
		$position = $this->repository->getByIdForAppAdmin($this->secondPosition->getId(), $this->secondAdmin);
		self::assertSame(20, $position->getOrderPiecesCount());
		self::assertSame($this->firstAdmin, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function testCurrentUserQueryDoesNotRetainPreviousOwner(): void
	{
		foreach ([$this->firstPosition, $this->secondPosition, $this->firstPosition] as $position) {
			$this->currentAppAdminGetter->setApiAppAdmin($position->getAppAdmin());
			$positions = $this->repository->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult();

			self::assertSame([$position->getId()->toString()], $this->positionIds($positions));
		}
	}

	public function testCurrentUserQueryRequiresLoggedInUser(): void
	{
		$this->expectException(AppAdminNotLoggedInException::class);

		$this->repository->createQueryBuilderForCurrentAppAdmin();
	}

	public function testOwnerQueryPreservesScopeWhenAddingFilters(): void
	{
		$queryBuilder = $this->repository->createQueryBuilderForAppAdmin($this->firstAdmin);
		$queryBuilder->innerJoin('stockPosition.stockAsset', 'stockAsset');
		$queryBuilder->andWhere('stockAsset.currency = :currency');
		$queryBuilder->setParameter('currency', CurrencyEnum::USD);
		$queryBuilder->andWhere('stockPosition.orderPiecesCount = :pieces');
		$queryBuilder->setParameter('pieces', 20);

		self::assertSame([], $queryBuilder->getQuery()->getResult());
	}

	public function testOwnerWithoutPositionsGetsEmptyList(): void
	{
		$admin = $this->createAppAdmin(false);
		$this->entityManager->flush();

		self::assertSame([], $this->repository->findAllForAppAdmin($admin));
	}

	public function testLoadsOwnedPositionWithPessimisticLock(): void
	{
		$this->entityManager->beginTransaction();
		try {
			$position = $this->repository->getByIdForAppAdmin(
				$this->firstPosition->getId(),
				$this->firstAdmin,
				LockModeEnum::PESSIMISTIC_READ,
			);

			self::assertSame($this->firstPosition->getId()->toString(), $position->getId()->toString());
		} finally {
			$this->entityManager->rollback();
		}
	}

	private function createAppAdmin(bool $sysAdmin): AppAdmin
	{
		$username = 'position-owner-' . Uuid::uuid4()->toString();
		$admin = new AppAdmin(
			$username,
			$username,
			$username . '@example.test',
			'unused-test-password',
			$this->createDatetime(),
			false,
			$sysAdmin,
		);
		$this->entityManager->persist($admin);

		return $admin;
	}

	private function createPosition(AppAdmin $appAdmin, StockAsset $asset, int $pieces): StockPosition
	{
		$position = new StockPosition(
			$appAdmin,
			$asset,
			$pieces,
			100.0,
			$this->createDatetime(),
			new AssetPriceEmbeddable($pieces * 100.0, CurrencyEnum::USD),
			false,
			$this->createDatetime(),
		);
		$this->entityManager->persist($position);

		return $position;
	}

	/**
	 * @param array<StockPosition> $positions
	 * @return array<string>
	 */
	private function positionIds(array $positions): array
	{
		return array_map(static fn (StockPosition $position): string => $position->getId()->toString(), $positions);
	}

}
