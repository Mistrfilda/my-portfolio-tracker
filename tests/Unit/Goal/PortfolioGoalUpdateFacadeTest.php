<?php

declare(strict_types = 1);

namespace App\Test\Unit\Goal;

use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalFacade;
use App\Goal\PortfolioGoalRepeatableEnum;
use App\Goal\PortfolioGoalRepository;
use App\Goal\PortfolioGoalTypeEnum;
use App\Goal\PortfolioGoalUpdateFacade;
use App\Goal\Resolver\PortfolioGoalResolver;
use App\Notification\NotificationFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class PortfolioGoalUpdateFacadeTest extends TestCase
{

	private PortfolioGoalUpdateFacade $portfolioGoalUpdateFacade;

	private PortfolioGoalRepository $portfolioGoalRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	private PortfolioGoalFacade $portfolioGoalFacade;

	private NotificationFacade $notificationFacade;

	protected function setUp(): void
	{
		$this->portfolioGoalRepository = $this->createMock(PortfolioGoalRepository::class);
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->datetimeFactory = $this->createMock(DatetimeFactory::class);
		$this->portfolioGoalFacade = $this->createMock(PortfolioGoalFacade::class);
		$this->notificationFacade = $this->createMock(NotificationFacade::class);

		$resolver = $this->createMock(PortfolioGoalResolver::class);
		$resolver->method('canResolveType')->willReturn(true);
		$resolver->method('resolve')->willReturn(100.0);

		$this->portfolioGoalUpdateFacade = new PortfolioGoalUpdateFacade(
			[$resolver],
			$this->portfolioGoalRepository,
			$this->entityManager,
			$this->datetimeFactory,
			$this->portfolioGoalFacade,
			$this->notificationFacade,
		);
	}

	public function testUpdateGoalSuccessfullyUpdatesGoal(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$portfolioGoal = $this->createMock(PortfolioGoal::class);
		$now = new ImmutableDateTime();

		$this->portfolioGoalRepository
			->expects($this->once())
			->method('getById')
			->with($portfolioGoalId)
			->willReturn($portfolioGoal);

		$this->datetimeFactory
			->expects($this->atLeastOnce())
			->method('createNow')
			->willReturn($now);

		$portfolioGoal
			->expects($this->once())
			->method('updateCurrentValue')
			->with(100.0, $now);

		$portfolioGoal
			->expects($this->once())
			->method('getEndDate')
			->willReturn(new ImmutableDateTime('+1 day'));

		$this->entityManager
			->expects($this->once())
			->method('flush');

		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
	}

	public function testUpdateGoalDoesNotUpdateWhenResolverCannotResolve(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$portfolioGoal = $this->createMock(PortfolioGoal::class);
		$now = new ImmutableDateTime();

		$resolver = $this->createMock(PortfolioGoalResolver::class);
		$resolver->method('canResolveType')->willReturn(false);

		$portfolioGoalUpdateFacade = new PortfolioGoalUpdateFacade(
			[$resolver],
			$this->portfolioGoalRepository,
			$this->entityManager,
			$this->datetimeFactory,
			$this->portfolioGoalFacade,
			$this->notificationFacade,
		);

		$this->portfolioGoalRepository
			->expects($this->once())
			->method('getById')
			->with($portfolioGoalId)
			->willReturn($portfolioGoal);

		$this->datetimeFactory
			->expects($this->atLeastOnce())
			->method('createNow')
			->willReturn($now);

		$portfolioGoal
			->expects($this->never())
			->method('updateCurrentValue');

		$portfolioGoal
			->expects($this->once())
			->method('getEndDate')
			->willReturn(new ImmutableDateTime('+1 day'));

		$this->entityManager
			->expects($this->never())
			->method('flush');

		$portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
	}

	public function testUpdateGoalThrowsExceptionIfPortfolioGoalNotFound(): void
	{
		$portfolioGoalId = Uuid::uuid4();

		$this->portfolioGoalRepository
			->expects($this->once())
			->method('getById')
			->with($portfolioGoalId)
			->willThrowException(new RuntimeException('Portfolio goal not found'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Portfolio goal not found');

		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
	}

	public function testUpdateGoalCreatesNewMonthlyGoalWhenCurrentGoalEnds(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$portfolioGoal = $this->createMock(PortfolioGoal::class);
		$now = new ImmutableDateTime();

		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		$portfolioGoal
			->method('getEndDate')
			->willReturn(new ImmutableDateTime('-1 day'));

		$portfolioGoal
			->method('getRepeatable')
			->willReturn(PortfolioGoalRepeatableEnum::MONTHLY);

		$portfolioGoal
			->expects($this->once())
			->method('updateCurrentValue')
			->with(100.0, $now);

		$portfolioGoal
			->expects($this->once())
			->method('endWithCurrentValue')
			->with($now);

		$newPortfolioGoalId = Uuid::uuid4();
		$newPortfolioGoal = $this->createMock(PortfolioGoal::class);
		$newPortfolioGoal->method('getId')->willReturn($newPortfolioGoalId);
		$newPortfolioGoal->method('getEndDate')->willReturn(new ImmutableDateTime('+1 month'));

		$this->portfolioGoalFacade
			->expects($this->once())
			->method('createNewMonthGoal')
			->with($portfolioGoalId)
			->willReturn($newPortfolioGoal);

		$this->portfolioGoalRepository
			->method('getById')
			->willReturnMap([
				[$portfolioGoalId, $portfolioGoal],
				[$newPortfolioGoalId, $newPortfolioGoal],
			]);

		$this->entityManager
			->expects($this->exactly(3))
			->method('flush');

		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
	}

	public function testUpdateGoalCreatesNewWeeklyGoalWhenCurrentGoalEnds(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$portfolioGoal = $this->createMock(PortfolioGoal::class);
		$now = new ImmutableDateTime();

		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		$portfolioGoal
			->method('getEndDate')
			->willReturn(new ImmutableDateTime('-1 day'));

		$portfolioGoal
			->method('getRepeatable')
			->willReturn(PortfolioGoalRepeatableEnum::WEEKLY);

		$portfolioGoal
			->expects($this->once())
			->method('updateCurrentValue')
			->with(100.0, $now);

		$portfolioGoal
			->expects($this->once())
			->method('endWithCurrentValue')
			->with($now);

		$newPortfolioGoalId = Uuid::uuid4();
		$newPortfolioGoal = $this->createMock(PortfolioGoal::class);
		$newPortfolioGoal->method('getId')->willReturn($newPortfolioGoalId);
		$newPortfolioGoal->method('getEndDate')->willReturn(new ImmutableDateTime('+1 week'));

		$this->portfolioGoalFacade
			->expects($this->once())
			->method('createNewWeeklyGoal')
			->with($portfolioGoalId)
			->willReturn($newPortfolioGoal);

		$this->portfolioGoalRepository
			->method('getById')
			->willReturnMap([
				[$portfolioGoalId, $portfolioGoal],
				[$newPortfolioGoalId, $newPortfolioGoal],
			]);

		$this->entityManager
			->expects($this->exactly(3))
			->method('flush');

		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
	}

	public function testUpdateGoalSendsNotificationWhenThresholdCrossed(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$now = new ImmutableDateTime();

		$portfolioGoal = new PortfolioGoal(
			$now->modify('-1 month'),
			$now->modify('+1 month'),
			PortfolioGoalTypeEnum::TOTAL_INVESTED_AMOUNT,
			200.0,
			null, // yearly (1% threshold)
			$now,
		);
		$portfolioGoal->start(100.0, 100.0, $now); // percentage 0%

		$this->portfolioGoalRepository
			->method('getById')
			->with($portfolioGoalId)
			->willReturn($portfolioGoal);

		$this->datetimeFactory
			->method('createNow')
			->willReturn($now);

		// First update: to 100.5 (0.5% - no notification for 1% threshold)
		$resolver = $this->createMock(PortfolioGoalResolver::class);
		$resolver->method('canResolveType')->willReturn(true);

		$value = 100.5;
		$resolver->method('resolve')->willReturnCallback(static function () use (&$value) {
			return $value;
		});

		$this->portfolioGoalUpdateFacade = new PortfolioGoalUpdateFacade(
			[$resolver],
			$this->portfolioGoalRepository,
			$this->entityManager,
			$this->datetimeFactory,
			$this->portfolioGoalFacade,
			$this->notificationFacade,
		);

		$this->notificationFacade->expects($this->exactly(1))->method('create');
		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);

		$this->assertEquals(0.0, $portfolioGoal->getLastNotifiedPercentage());

		// Second update: to 101.0 (1% -> notify!)
		$value = 101.0;
		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
		$this->assertEquals(1.0, (float) $value - 100);
		$this->assertEquals(1.0, $portfolioGoal->getLastNotifiedPercentage());
	}

}
