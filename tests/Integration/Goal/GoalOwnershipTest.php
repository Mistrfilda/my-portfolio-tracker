<?php

declare(strict_types = 1);

namespace App\Test\Integration\Goal;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalFacade;
use App\Goal\PortfolioGoalRepeatableEnum;
use App\Goal\PortfolioGoalRepository;
use App\Goal\PortfolioGoalTypeEnum;
use App\Goal\PortfolioGoalUpdateFacade;
use App\Goal\Resolver\PortfolioGoalMonthlyIncomeResolver;
use App\Goal\Resolver\PortfolioGoalTotalDividendAmountResolver;
use App\Goal\Resolver\PortfolioGoalTotalIncomeResolver;
use App\Goal\Resolver\PortfolioGoalTotalInvestedAmountResolver;
use App\Notification\NotificationFacade;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Test\Integration\Asset\PositionOwnershipFixture;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\TestWith;

class GoalOwnershipTest extends IntegrationTestCase
{

	private EntityManagerInterface $em;

	private CurrentAppAdminGetter $currentAdmin;

	private PositionOwnershipFixture $fixture;

	private ImmutableDateTime $now;

	/** @var array<PortfolioGoal> */
	private array $goals = [];

	/** @var array<AppAdmin> */
	private array $recipients = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$this->fixture = new PositionOwnershipFixture($this->em);
		$this->now = new ImmutableDateTime('2026-06-15');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($this->now);
		$this->replaceService(DatetimeFactory::class, $clock);
		$notifications = $this->createStub(NotificationFacade::class);
		$notifications->method('createForRecipient')->willReturnCallback(function (AppAdmin $owner): void {
			$this->recipients[] = $owner;
		});
		$this->replaceService(NotificationFacade::class, $notifications);
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
		$event = new StockAssetDividend(
			$this->fixture->stock,
			$this->now,
			$this->now,
			null,
			CurrencyEnum::CZK,
			10.0,
			$this->now,
		);
		$this->em->persist($event);
		foreach ($this->fixture->admins as $index => $owner) {
			$this->em->persist(new WorkMonthlyIncome($owner, 2026, 6, ($index + 1) * 10.0, 100, $this->now));
			$this->em->persist(new StockAssetDividendRecord(
				$event,
				10,
				($index + 1) * 100.0,
				CurrencyEnum::CZK,
				($index + 1) * 100.0,
				CurrencyEnum::CZK,
				$this->now,
				$owner,
			));
			$goal = $this->goal($owner);
			$goal->start(0, 0, $this->now);
			$this->goals[] = $goal;
		}

		$this->em->flush();
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testListsIdsAndMutationsAreScopedAndEditingKeepsOwner(): void
	{
		$repository = $this->getService(PortfolioGoalRepository::class);
		$facade = $this->getService(PortfolioGoalFacade::class);
		$updates = $this->getService(PortfolioGoalUpdateFacade::class);
		foreach ([0, 1, 0] as $index) {
			$owner = $this->fixture->admins[$index];
			$this->currentAdmin->setApiAppAdmin($owner);
			$goal = $this->goals[$index];
			self::assertSame([$goal], $repository->findAll());
			self::assertSame([$goal], $repository->findActive($this->now));
			self::assertSame($goal, $repository->findFirstActiveByType($goal->getType()));
			self::assertSame(
				[$goal],
				$repository->findByIds(array_map(static fn (PortfolioGoal $g) => $g->getId(), $this->goals)),
			);
			$foreign = $this->goals[1 - $index]->getId();
			foreach ([
				static fn () => $repository->getById($foreign),
				static fn () => $updates->startGoal($foreign),
				static fn () => $updates->endGoal($foreign),
				static fn () => $updates->updateGoal($foreign),
				static fn () => $facade->update($foreign, $goal->getStartDate(), $goal->getEndDate(), 1, null),
			] as $attempt) {
				$this->assertNotFound($attempt);
			}

			$facade->update($goal->getId(), $goal->getStartDate(), $goal->getEndDate(), 5000, null);
			self::assertSame($owner, $goal->getAppAdmin());
		}

		$facade->create($this->now, $this->now->modify('+1 month'), PortfolioGoalTypeEnum::TOTAL_INCOME, 1000, null);
		self::assertCount(2, $repository->findAll());
		foreach ($repository->findAll() as $goal) {
			self::assertSame($this->fixture->admins[0], $goal->getAppAdmin());
		}
	}

	public function testCalculationsAndNotificationsUsePersistedOwnerWithoutSession(): void
	{
		$updates = $this->getService(PortfolioGoalUpdateFacade::class);
		$updates->updateAllActive($this->fixture->admins[0]->getId());
		self::assertSame(100.0, $this->goals[0]->getCurrentValue());
		self::assertSame(0.0, $this->goals[1]->getCurrentValue());
		self::assertSame([$this->fixture->admins[0]], $this->recipients);
		$updates->updateAllActive($this->fixture->admins[1]->getId());
		self::assertSame(200.0, $this->goals[1]->getCurrentValue());
		self::assertSame($this->fixture->admins, $this->recipients);
		$updates->updateAllActive($this->fixture->admins[1]->getId());
		$updates->updateAllActive($this->fixture->admins[0]->getId());
		self::assertCount(2, $this->recipients);
		foreach ($this->fixture->admins as $index => $owner) {
			$goal = $this->goals[$index];
			self::assertSame(
				($index + 1) * 100.0,
				$this->getService(PortfolioGoalTotalDividendAmountResolver::class)->resolve($goal),
			);
			self::assertSame(
				($index + 1) * 1000.0,
				$this->getService(PortfolioGoalMonthlyIncomeResolver::class)->resolve($goal),
			);
			self::assertSame(
				($index + 1) * 1000.0,
				$this->getService(PortfolioGoalTotalIncomeResolver::class)->resolve($goal),
			);
			// Only the owner's open Portu investment remains in this fixture.
			self::assertSame(
				1100.0,
				$this->getService(PortfolioGoalTotalInvestedAmountResolver::class)->resolve($goal),
			);
		}
	}

	#[TestWith([PortfolioGoalRepeatableEnum::MONTHLY])]
	#[TestWith([PortfolioGoalRepeatableEnum::WEEKLY])]
	public function testRepeatingGoalPreservesOwnerAndRetryDoesNotCreateAnotherGoal(
		PortfolioGoalRepeatableEnum $repeat,
	): void
	{
		$owner = $this->fixture->admins[1];
		$goal = $this->goal($owner, $repeat);
		$goal->start(0, 0, $this->now);
		$this->em->flush();
		$updates = $this->getService(PortfolioGoalUpdateFacade::class);
		$updates->updateAllActive($owner->getId());
		self::assertFalse($goal->isActive());
		$active = $this->getService(PortfolioGoalRepository::class)->findActive($this->now->modify('+1 day'), $owner);
		self::assertCount(2, $active);
		foreach ($active as $newGoal) {
			self::assertSame($owner, $newGoal->getAppAdmin());
		}

		$updates->updateAllActive($owner->getId());
		self::assertCount(
			2,
			$this->getService(PortfolioGoalRepository::class)->findActive($this->now->modify('+1 day'), $owner),
		);
	}

	public function testMissingCurrentUserDoesNotReadAllGoals(): void
	{
		$this->expectException(AppAdminNotLoggedInException::class);
		$this->getService(PortfolioGoalRepository::class)->findAll();
	}

	private function goal(AppAdmin $owner, PortfolioGoalRepeatableEnum|null $repeat = null): PortfolioGoal
	{
		$goal = new PortfolioGoal(
			$owner,
			new ImmutableDateTime('2026-06-01'),
			$repeat === null ? new ImmutableDateTime('2026-06-30') : new ImmutableDateTime('2026-06-07'),
			PortfolioGoalTypeEnum::TOTAL_DIVIDEND_AMOUNT,
			1000,
			$repeat,
			$this->now,
		);
		$this->em->persist($goal);
		return $goal;
	}

	private function assertNotFound(callable $attempt): void
	{
		try {
			$attempt();
		} catch (NoEntityFoundException) {
			self::assertTrue(true);
			return;
		}

		self::fail('A foreign goal must be inaccessible.');
	}

	/** @param class-string $type */
	private function replaceService(string $type, object $service): void
	{
		foreach ($this->container->findByType($type) as $name) {
			$this->container->removeService($name);
			$this->container->addService($name, $service);
		}
	}

}
