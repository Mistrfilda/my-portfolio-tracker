<?php

declare(strict_types = 1);

namespace App\Test\Unit\Goal;

use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalRepository;
use App\Goal\PortfolioGoalUpdateFacade;
use App\Goal\Resolver\PortfolioGoalResolver;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class PortfolioGoalUpdateFacadeTest extends TestCase
{

	private PortfolioGoalUpdateFacade $portfolioGoalUpdateFacade;

	private PortfolioGoalRepository $portfolioGoalRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	protected function setUp(): void
	{
		$this->portfolioGoalRepository = $this->createMock(PortfolioGoalRepository::class);
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->datetimeFactory = $this->createMock(DatetimeFactory::class);

		$resolver = $this->createMock(PortfolioGoalResolver::class);
		$resolver->method('canResolveType')->willReturn(true);
		$resolver->method('resolve')->willReturn(100.0);

		$this->portfolioGoalUpdateFacade = new PortfolioGoalUpdateFacade(
			[$resolver],
			$this->portfolioGoalRepository,
			$this->entityManager,
			$this->datetimeFactory,
		);
	}

	public function testUpdateGoalSuccessfullyUpdatesGoal(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$portfolioGoal = $this->createMock(PortfolioGoal::class);

		$this->portfolioGoalRepository
			->expects($this->once())
			->method('getById')
			->with($portfolioGoalId)
			->willReturn($portfolioGoal);

		$portfolioGoal
			->expects($this->once())
			->method('updateCurrentValue');

		$this->datetimeFactory
			->expects($this->once())
			->method('createNow');

		$this->entityManager
			->expects($this->once())
			->method('flush');

		$this->portfolioGoalUpdateFacade->updateGoal($portfolioGoalId);
	}

	public function testUpdateGoalDoesNotUpdateWhenResolverCannotResolve(): void
	{
		$portfolioGoalId = Uuid::uuid4();
		$portfolioGoal = $this->createMock(PortfolioGoal::class);

		$resolver = $this->createMock(PortfolioGoalResolver::class);
		$resolver->method('canResolveType')->willReturn(false);

		$portfolioGoalUpdateFacade = new PortfolioGoalUpdateFacade(
			[$resolver],
			$this->portfolioGoalRepository,
			$this->entityManager,
			$this->datetimeFactory,
		);

		$this->portfolioGoalRepository
			->expects($this->once())
			->method('getById')
			->with($portfolioGoalId)
			->willReturn($portfolioGoal);

		$portfolioGoal
			->expects($this->never())
			->method('updateCurrentValue');

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

}
