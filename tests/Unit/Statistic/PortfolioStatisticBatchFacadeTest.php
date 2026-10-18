<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Statistic\Performance\PortfolioPerformanceEventProvider;
use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\PortfolioStatisticBatchFacade;
use App\Statistic\PortfolioStatisticFacade;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DatabaseException;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class PortfolioStatisticBatchFacadeTest extends TestCase
{

	private int $clears = 0;

	/** @var array<string> */
	private array $invalidatedTotals = [];

	/** @var array<string> */
	private array $invalidatedEvents = [];

	/** @var array<string> */
	private array $failedOwners = [];

	public function testEachOwnerFinishesTheSequenceBeforeTheNextOwnerStarts(): void
	{
		$owners = [$this->owner(), $this->owner()];
		$steps = [];
		$statistics = $this->createMock(PortfolioStatisticFacade::class);
		$statistics->expects(self::exactly(2))->method('saveCurrentDashboardValues')
			->willReturnCallback(
				static function (AppAdmin $owner, bool $includePerformance) use (&$steps): PortfolioStatisticRecord {
					self::assertFalse($includePerformance);
					$steps[] = 'save:' . $owner->getId();
					return new PortfolioStatisticRecord($owner, new ImmutableDateTime('2026-10-18'));
				},
			);
		$statistics->expects(self::exactly(2))->method('appendPortfolioPerformanceValues')
			->willReturnCallback(static function (PortfolioStatisticRecord $record) use (&$steps): void {
				$steps[] = 'append:' . $record->getAppAdmin()->getId();
			});
		$performance = $this->createMock(PortfolioPerformanceRebuildFacade::class);
		$performance->expects(self::exactly(2))->method('rebuild')
			->willReturnCallback(static function (AppAdmin $owner) use (&$steps): int {
				$steps[] = 'rebuild:' . $owner->getId();
				return 2;
			});

		self::assertTrue($this->batch($owners, $statistics, $performance)->saveForAll());
		self::assertSame([
			'save:' . $owners[0]->getId(), 'rebuild:' . $owners[0]->getId(), 'append:' . $owners[0]->getId(),
			'save:' . $owners[1]->getId(), 'rebuild:' . $owners[1]->getId(), 'append:' . $owners[1]->getId(),
		], $steps);
		self::assertSame(2, $this->clears);
	}

	#[TestWith(['save'])]
	#[TestWith(['rebuild'])]
	#[TestWith(['append'])]
	public function testAccountFailureSkipsItsRemainingStepsAndContinuesWithTheNextOwner(string $failureStage): void
	{
		$owners = [$this->owner(), $this->owner()];
		$steps = [];
		$step = static function (string $stage, AppAdmin $owner) use (&$steps, $owners, $failureStage): void {
			$steps[] = $stage . ':' . $owner->getId();
			if ($owner === $owners[0] && $stage === $failureStage) {
				throw new RuntimeException('Invalid personal history');
			}
		};
		$statistics = $this->createStub(PortfolioStatisticFacade::class);
		$statistics->method('saveCurrentDashboardValues')->willReturnCallback(
			static function (AppAdmin $owner) use ($step): PortfolioStatisticRecord {
				$step('save', $owner);
				return new PortfolioStatisticRecord($owner, new ImmutableDateTime('2026-10-18'));
			},
		);
		$statistics->method('appendPortfolioPerformanceValues')->willReturnCallback(
			static fn (PortfolioStatisticRecord $record) => $step('append', $record->getAppAdmin()),
		);
		$performance = $this->createStub(PortfolioPerformanceRebuildFacade::class);
		$performance->method('rebuild')->willReturnCallback(static function (AppAdmin $owner) use ($step): int {
			$step('rebuild', $owner);
			return 1;
		});

		self::assertFalse($this->batch($owners, $statistics, $performance)->saveForAll());
		$firstStages = array_slice(
			['save', 'rebuild', 'append'],
			0,
			array_search($failureStage, ['save', 'rebuild', 'append'], true) + 1,
		);
		self::assertSame([
			...array_map(static fn (string $stage): string => $stage . ':' . $owners[0]->getId(), $firstStages),
			'save:' . $owners[1]->getId(), 'rebuild:' . $owners[1]->getId(), 'append:' . $owners[1]->getId(),
		], $steps);
		self::assertSame([$owners[0]->getId()->toString()], $this->failedOwners);
		self::assertSame(
			array_map(static fn (AppAdmin $owner): string => $owner->getId()->toString(), $owners),
			$this->invalidatedTotals,
		);
		self::assertSame($this->invalidatedTotals, $this->invalidatedEvents);
		self::assertSame(2, $this->clears);
	}

	#[TestWith([true])]
	#[TestWith([false])]
	public function testPersistenceFailureStopsBeforeTheNextOwner(bool $managerOpen): void
	{
		$owners = [$this->owner(), $this->owner()];
		$exception = $managerOpen
			? new class ('Database failed') extends RuntimeException implements DatabaseException {

			}
			: new RuntimeException('Flush closed the entity manager');
		$statistics = $this->createMock(PortfolioStatisticFacade::class);
		$statistics->expects(self::once())->method('saveCurrentDashboardValues')->willThrowException($exception);
		$statistics->expects(self::never())->method('appendPortfolioPerformanceValues');
		$performance = $this->createMock(PortfolioPerformanceRebuildFacade::class);
		$performance->expects(self::never())->method('rebuild');

		self::assertFalse($this->batch($owners, $statistics, $performance, $managerOpen)->saveForAll());
		self::assertSame(1, $this->clears);
		self::assertSame([$owners[0]->getId()->toString()], $this->invalidatedTotals);
	}

	public function testRebuildOnlyNeverCreatesOrAppendsSnapshots(): void
	{
		$owners = [$this->owner(), $this->owner()];
		$statistics = $this->createMock(PortfolioStatisticFacade::class);
		$statistics->expects(self::never())->method('saveCurrentDashboardValues');
		$statistics->expects(self::never())->method('appendPortfolioPerformanceValues');
		$performance = $this->createMock(PortfolioPerformanceRebuildFacade::class);
		$performance->expects(self::exactly(2))->method('rebuild')->willReturn(0);

		self::assertTrue($this->batch($owners, $statistics, $performance)->rebuildForAll());
		self::assertSame(2, $this->clears);
	}

	public function testNoAccountsIsSuccessfulWithoutCalculations(): void
	{
		$statistics = $this->createMock(PortfolioStatisticFacade::class);
		$statistics->expects(self::never())->method('saveCurrentDashboardValues');
		$performance = $this->createMock(PortfolioPerformanceRebuildFacade::class);
		$performance->expects(self::never())->method('rebuild');

		self::assertTrue($this->batch([], $statistics, $performance)->saveForAll());
		self::assertSame(0, $this->clears);
	}

	/** @param array<AppAdmin> $owners */
	private function batch(
		array $owners,
		PortfolioStatisticFacade $statistics,
		PortfolioPerformanceRebuildFacade $performance,
		bool $managerOpen = true,
	): PortfolioStatisticBatchFacade
	{
		$repository = $this->createStub(AppAdminRepository::class);
		$repository->method('findAll')->willReturn($owners);
		$repository->method('findById')->willReturnCallback(
			static function (UuidInterface $id) use ($owners): AppAdmin {
				foreach ($owners as $owner) {
					if ($owner->getId()->equals($id)) {
						return $owner;
					}
				}

				throw new RuntimeException('Unexpected owner');
			},
		);
		$connection = $this->createStub(Connection::class);
		$connection->method('transactional')->willReturnCallback(
			static fn (callable $operation): mixed => $operation(),
		);
		$em = $this->createStub(EntityManagerInterface::class);
		$em->method('getConnection')->willReturn($connection);
		$em->method('isOpen')->willReturn($managerOpen);
		$em->method('clear')->willReturnCallback(function (): void {
			$this->clears++;
		});
		$total = $this->createStub(PortfolioStatisticTotalValueProvider::class);
		$total->method('invalidateCache')->willReturnCallback(function (AppAdmin $owner): void {
			$this->invalidatedTotals[] = $owner->getId()->toString();
		});
		$events = $this->createStub(PortfolioPerformanceEventProvider::class);
		$events->method('invalidateCache')->willReturnCallback(function (AppAdmin $owner): void {
			$this->invalidatedEvents[] = $owner->getId()->toString();
		});
		$logger = $this->createStub(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(function (string $message, array $context = []): void {
			if (isset($context['appAdminId'])) {
				$this->failedOwners[] = $context['appAdminId'];
			}
		});
		return new PortfolioStatisticBatchFacade($repository, $statistics, $performance, $em, $total, $events, $logger);
	}

	private function owner(): AppAdmin
	{
		$owner = $this->createStub(AppAdmin::class);
		$owner->method('getId')->willReturn(Uuid::uuid4());
		return $owner;
	}

}
