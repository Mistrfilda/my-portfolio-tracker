<?php

declare(strict_types = 1);

namespace App\Goal;

use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

class PortfolioGoalFacade
{

	public function __construct(
		private PortfolioGoalRepository $portfolioGoalRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimefactory,
	)
	{
	}

	public function create(
		ImmutableDateTime $startDate,
		ImmutableDateTime $endDate,
		PortfolioGoalTypeEnum $type,
		float $goal,
		PortfolioGoalRepeatableEnum|null $repeatable,
	): void
	{
		$portfolioGoal = new PortfolioGoal(
			$startDate,
			$endDate,
			$type,
			$goal,
			$repeatable,
			$this->datetimefactory->createNow(),
		);

		$this->entityManager->persist($portfolioGoal);
		$this->entityManager->flush();
	}

	public function update(
		UuidInterface $id,
		ImmutableDateTime $startDate,
		ImmutableDateTime $endDate,
		float $goal,
		PortfolioGoalRepeatableEnum|null $repeatable,
	): void
	{
		$portfolioGoal = $this->portfolioGoalRepository->getById($id);
		$portfolioGoal->update(
			$startDate,
			$endDate,
			$goal,
			$repeatable,
			$this->datetimefactory->createNow(),
		);
		$this->entityManager->flush();
	}

	public function createNewMonthGoal(UuidInterface $portfolioGoalId): PortfolioGoal
	{
		$now = $this->datetimefactory->createNow();
		$portfolioGoal = $this->portfolioGoalRepository->getById($portfolioGoalId);

		$newPortfolioGoal = new PortfolioGoal(
			$now->modify('first day of this month'),
			$now->modify('last day of this month'),
			$portfolioGoal->getType(),
			$portfolioGoal->getGoal(),
			$portfolioGoal->getRepeatable(),
			$this->datetimefactory->createNow(),
		);

		$this->entityManager->persist($newPortfolioGoal);
		$newPortfolioGoal->start(0, 0, $now);
		$this->entityManager->flush();
		return $newPortfolioGoal;
	}

	public function createNewWeeklyGoal(UuidInterface $portfolioGoalId): PortfolioGoal
	{
		$now = $this->datetimefactory->createNow();
		$portfolioGoal = $this->portfolioGoalRepository->getById($portfolioGoalId);

		$newPortfolioGoal = new PortfolioGoal(
			$now->modify('monday this week'),
			$now->modify('sunday this week'),
			$portfolioGoal->getType(),
			$portfolioGoal->getGoal(),
			$portfolioGoal->getRepeatable(),
			$this->datetimefactory->createNow(),
		);

		$this->entityManager->persist($newPortfolioGoal);
		$newPortfolioGoal->start(0, 0, $now);
		$this->entityManager->flush();
		return $newPortfolioGoal;
	}

}
