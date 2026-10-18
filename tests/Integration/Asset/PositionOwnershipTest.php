<?php

declare(strict_types = 1);

namespace App\Test\Integration\Asset;

use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Position\Closed\CryptoClosedPosition;
use App\Crypto\Position\Closed\CryptoClosedPositionFacade;
use App\Crypto\Position\Closed\CryptoClosedPositionRepository;
use App\Crypto\Position\CryptoPositionFacade;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyEnum;
use App\Doctrine\Entity;
use App\Doctrine\NoEntityFoundException;
use App\JobRequest\JobRequestFacade;
use App\Portu\Position\PortuPositionFacade;
use App\Portu\Position\PortuPositionRepository;
use App\Portu\Position\UI\PortuPositionPriceFormFactory;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Position\StockPositionRepository;
use App\Test\Integration\IntegrationTestCase;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

class PositionOwnershipTest extends IntegrationTestCase
{

	private PositionOwnershipFixture $fixture;

	private CurrentAppAdminGetter $currentAdmin;

	protected function setUp(): void
	{
		parent::setUp();
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
		$this->fixture = new PositionOwnershipFixture($this->getService(EntityManagerInterface::class));

		foreach ($this->container->findByType(JobRequestFacade::class) as $serviceName) {
			$this->container->removeService($serviceName);
			$this->container->addService($serviceName, $this->createStub(JobRequestFacade::class));
		}
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testCurrentUserCanReadOwnPositionsClosuresAndHistory(): void
	{
		foreach ($this->fixture->admins as $index => $admin) {
			$this->currentAdmin->setApiAppAdmin($admin);
			$records = array_map(
				static fn (Closure $load): Entity|StockClosedPosition|CryptoClosedPosition => $load(),
				$this->recordLoaders($index),
			);
			self::assertSame($this->recordIds($index), $this->ids($records));
		}
	}

	public function testCurrentUserCannotReadAnotherOwnersRecordsEvenAsSysAdmin(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		self::assertTrue($this->fixture->admins[0]->isSysAdmin());
		foreach ($this->recordLoaders(1) as $load) {
			$this->assertAccessFails($load, NoEntityFoundException::class);
		}
	}

	public function testRecordAccessRequiresAuthenticatedUser(): void
	{
		foreach ($this->recordLoaders(0) as $load) {
			$this->assertAccessFails($load, AppAdminNotLoggedInException::class);
		}
	}

	public function testExplicitOwnerAccessWorksWithoutLogin(): void
	{
		$admin = $this->fixture->admins[1];
		$records = [
			$this->getService(StockPositionRepository::class)->getByIdForAppAdmin(
				$this->fixture->stockPositions[1]->getId(),
				$admin,
			),
			$this->getService(CryptoPositionRepository::class)->getByIdForAppAdmin(
				$this->fixture->cryptoPositions[1]->getId(),
				$admin,
			),
			$this->getService(PortuPositionRepository::class)->getByIdForAppAdmin(
				$this->fixture->portuPositions[1]->getId(),
				$admin,
			),
			$this->getService(StockClosedPositionRepository::class)->getByIdForAppAdmin(
				$this->fixture->stockClosedPositions[1]->getId(),
				$admin,
			),
			$this->getService(CryptoClosedPositionRepository::class)->getByIdForAppAdmin(
				$this->fixture->cryptoClosedPositions[1]->getId(),
				$admin,
			),
			$this->getService(PortuAssetPriceRecordRepository::class)->getByIdForAppAdmin(
				$this->fixture->portuPrices[1]->getId(),
				$admin,
			),
		];

		self::assertSame($this->recordIds(1), $this->ids($records));
		self::assertFalse($this->currentAdmin->isLoggedIn());
	}

	public function testDatagridsOnlyContainCurrentUsersPositionsAndHistory(): void
	{
		foreach ($this->fixture->admins as $index => $admin) {
			$this->currentAdmin->setApiAppAdmin($admin);
			$queries = [
				$this->getService(StockPositionRepository::class)->createQueryBuilderForDatagrid(),
				$this->getService(CryptoPositionRepository::class)->createQueryBuilderForDatagrid(),
				$this->getService(PortuPositionRepository::class)->createQueryBuilderForDatagrid(
					$this->fixture->portu->getId(),
				),
				$this->getService(PortuAssetPriceRecordRepository::class)->createQueryBuilderForDatagrid(
					$this->fixture->portuPositions[$index]->getId(),
				),
			];
			$expected = [$this->recordIds($index)[0], $this->recordIds($index)[1], $this->recordIds(
				$index,
			)[2], $this->recordIds($index)[5]];
			foreach ($queries as $queryIndex => $query) {
				self::assertSame([$expected[$queryIndex]], $this->ids($query->getQuery()->getResult()));
			}

			self::assertSame([], $this->getService(PortuAssetPriceRecordRepository::class)
				->createQueryBuilderForDatagrid($this->fixture->portuPositions[1 - $index]->getId())
				->getQuery()->getResult());
		}
	}

	public function testBulkLookupsExcludeForeignIds(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$results = [
			$this->getService(StockPositionRepository::class)->findByIds(
				[$this->fixture->stockPositions[0]->getId(), $this->fixture->stockPositions[1]->getId()],
			),
			$this->getService(CryptoPositionRepository::class)->findByIds(
				[$this->fixture->cryptoPositions[0]->getId(), $this->fixture->cryptoPositions[1]->getId()],
			),
			$this->getService(PortuPositionRepository::class)->findByIds(
				[$this->fixture->portuPositions[0]->getId(), $this->fixture->portuPositions[1]->getId()],
			),
			$this->getService(StockClosedPositionRepository::class)->findByIds(
				[$this->fixture->stockClosedPositions[0]->getId(), $this->fixture->stockClosedPositions[1]->getId()],
			),
			$this->getService(CryptoClosedPositionRepository::class)->findByIds(
				[$this->fixture->cryptoClosedPositions[0]->getId(), $this->fixture->cryptoClosedPositions[1]->getId()],
			),
			$this->getService(PortuAssetPriceRecordRepository::class)->findByIds(
				[$this->fixture->portuPrices[0]->getId(), $this->fixture->portuPrices[1]->getId()],
			),
		];
		foreach ($results as $index => $records) {
			self::assertSame([$this->recordIds(0)[$index]], $this->ids($records));
		}
	}

	public function testForeignPositionsCannotBeUpdatedOrClosed(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$date = $this->createDatetime('2026-02-01');
		$amount = new AssetPriceEmbeddable(100.0, CurrencyEnum::USD);
		$operations = [
			fn () => $this->getService(StockPositionFacade::class)->update(
				$this->fixture->stockPositions[1]->getId(),
				$this->fixture->stock->getId(),
				1,
				1.0,
				$date,
				$amount,
				false,
			),
			fn () => $this->getService(CryptoPositionFacade::class)->update(
				$this->fixture->cryptoPositions[1]->getId(),
				$this->fixture->crypto->getId(),
				1.0,
				1.0,
				$date,
				$amount,
				false,
			),
			fn () => $this->getService(PortuPositionFacade::class)->update(
				$this->fixture->portuPositions[1]->getId(),
				$this->fixture->portu->getId(),
				$date,
				1.0,
				1.0,
				1.0,
				1.0,
			),
			fn () => $this->getService(StockClosedPositionFacade::class)->create(
				$this->fixture->stockPositions[1]->getId(),
				1.0,
				$date,
				$amount,
				false,
			),
			fn () => $this->getService(CryptoClosedPositionFacade::class)->create(
				$this->fixture->cryptoPositions[1]->getId(),
				1.0,
				$date,
				$amount,
				false,
			),
			fn () => $this->getService(StockClosedPositionFacade::class)->update(
				$this->fixture->stockClosedPositions[1]->getId(),
				1.0,
				$date,
				$amount,
				false,
			),
			fn () => $this->getService(CryptoClosedPositionFacade::class)->update(
				$this->fixture->cryptoClosedPositions[1]->getId(),
				1.0,
				$date,
				$amount,
				false,
			),
			fn () => $this->getService(PortuPositionFacade::class)->updatePriceForDate(
				$this->fixture->portuPositions[1]->getId(),
				$date,
				1.0,
				1.0,
				true,
			),
		];
		foreach ($operations as $operation) {
			$this->assertAccessFails($operation, NoEntityFoundException::class);
		}

		self::assertSame(20, $this->fixture->stockPositions[1]->getOrderPiecesCount());
		self::assertSame(1.0, $this->fixture->cryptoPositions[1]->getOrderPiecesCount());
		self::assertSame(1200.0, $this->fixture->portuPositions[1]->getCurrentValue()->getPrice());
	}

	public function testOwnPositionsCanBeUpdatedWithoutChangingOwner(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$date = $this->createDatetime('2026-02-01');
		$amount = new AssetPriceEmbeddable(200.0, CurrencyEnum::USD);
		$stock = $this->getService(StockPositionFacade::class)->update(
			$this->fixture->stockPositions[0]->getId(),
			$this->fixture->stock->getId(),
			2,
			100.0,
			$date,
			$amount,
			false,
		);
		$crypto = $this->getService(CryptoPositionFacade::class)->update(
			$this->fixture->cryptoPositions[0]->getId(),
			$this->fixture->crypto->getId(),
			2.0,
			100.0,
			$date,
			$amount,
			false,
		);
		$portu = $this->getService(PortuPositionFacade::class)->update(
			$this->fixture->portuPositions[0]->getId(),
			$this->fixture->portu->getId(),
			$date,
			1000.0,
			100.0,
			1500.0,
			1300.0,
		);
		foreach ([$stock, $crypto, $portu] as $position) {
			self::assertTrue($this->fixture->admins[0]->getId()->equals($position->getAppAdmin()->getId()));
		}

		self::assertSame(2, $stock->getOrderPiecesCount());
		self::assertSame(2.0, $crypto->getOrderPiecesCount());
		self::assertSame(1500.0, $portu->getCurrentValue()->getPrice());
	}

	public function testCreatedAndClosedPositionsKeepCurrentOwner(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$date = $this->createDatetime('2026-02-01');
		$amount = new AssetPriceEmbeddable(200.0, CurrencyEnum::USD);
		$stock = $this->getService(StockPositionFacade::class)->create(
			$this->fixture->stock->getId(),
			2,
			100.0,
			$date,
			$amount,
			false,
		);
		$crypto = $this->getService(CryptoPositionFacade::class)->create(
			$this->fixture->crypto->getId(),
			2.0,
			100.0,
			$date,
			$amount,
			false,
		);
		$portu = $this->getService(PortuPositionFacade::class)->create(
			$this->fixture->portu->getId(),
			$date,
			1000.0,
			100.0,
			1100.0,
			1000.0,
		);
		foreach ([$stock, $crypto, $portu] as $position) {
			self::assertTrue($this->fixture->admins[1]->getId()->equals($position->getAppAdmin()->getId()));
		}

		$stockClosed = $this->getService(StockClosedPositionFacade::class)->create(
			$stock->getId(),
			110.0,
			$date,
			$amount,
			false,
		);
		$cryptoClosed = $this->getService(CryptoClosedPositionFacade::class)->create(
			$crypto->getId(),
			110.0,
			$date,
			$amount,
			false,
		);
		foreach ([$stockClosed, $cryptoClosed] as $closed) {
			self::assertTrue(
				$this->fixture->admins[1]->getId()->equals($closed->getAssetPositon()->getAppAdmin()->getId()),
			);
		}
	}

	public function testPortuPriceUpdateDoesNotTouchOtherOwnersRecordOnSameDate(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$price = $this->getService(PortuPositionFacade::class)->updatePriceForDate(
			$this->fixture->portuPositions[0]->getId(),
			$this->createDatetime('2026-01-15'),
			2500.0,
			2000.0,
			true,
		);

		self::assertSame($this->fixture->portuPrices[0]->getId(), $price->getId());
		self::assertSame(2500.0, $this->fixture->portuPositions[0]->getCurrentValue()->getPrice());
		self::assertSame(1200.0, $this->fixture->portuPositions[1]->getCurrentValue()->getPrice());
		self::assertSame(1200.0, $this->fixture->portuPrices[1]->getCurrentValue()->getPrice());
	}

	public function testPortuFormRejectsPriceFromAnotherPositionOfSameOwner(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$otherPosition = $this->fixture->createPortuPosition($this->fixture->admins[0]);
		$otherPrice = $this->fixture->createPortuPrice($otherPosition);
		$this->getService(EntityManagerInterface::class)->flush();
		$this->expectException(NoEntityFoundException::class);

		$this->getService(PortuPositionPriceFormFactory::class)->create(
			$this->fixture->portuPositions[0]->getId(),
			$otherPrice->getId(),
			static function (): void {
			},
		);
	}

	/** @return array<Closure(): (Entity|StockClosedPosition|CryptoClosedPosition)> */
	private function recordLoaders(int $index): array
	{
		return [
			fn () => $this->getService(StockPositionRepository::class)->getById(
				$this->fixture->stockPositions[$index]->getId(),
			),
			fn () => $this->getService(CryptoPositionRepository::class)->getById(
				$this->fixture->cryptoPositions[$index]->getId(),
			),
			fn () => $this->getService(PortuPositionRepository::class)->getById(
				$this->fixture->portuPositions[$index]->getId(),
			),
			fn () => $this->getService(StockClosedPositionRepository::class)->getById(
				$this->fixture->stockClosedPositions[$index]->getId(),
			),
			fn () => $this->getService(CryptoClosedPositionRepository::class)->getById(
				$this->fixture->cryptoClosedPositions[$index]->getId(),
			),
			fn () => $this->getService(PortuAssetPriceRecordRepository::class)->getById(
				$this->fixture->portuPrices[$index]->getId(),
			),
		];
	}

	/** @return array<string> */
	private function recordIds(int $index): array
	{
		return $this->ids([
			$this->fixture->stockPositions[$index], $this->fixture->cryptoPositions[$index], $this->fixture->portuPositions[$index],
			$this->fixture->stockClosedPositions[$index], $this->fixture->cryptoClosedPositions[$index], $this->fixture->portuPrices[$index],
		]);
	}

	/**
	 * @param array<Entity|StockClosedPosition|CryptoClosedPosition> $records
	 * @return array<string>
	 */
	private function ids(array $records): array
	{
		return array_map(
			static fn (Entity|StockClosedPosition|CryptoClosedPosition $record): string => (string) $record->getId(),
			$records,
		);
	}

	/** @param class-string<Throwable> $exceptionClass */
	private function assertAccessFails(Closure $operation, string $exceptionClass): void
	{
		try {
			$operation();
		} catch (Throwable $exception) {
			self::assertInstanceOf($exceptionClass, $exception);
			return;
		}

		self::fail('Access to the record should have been rejected.');
	}

}
