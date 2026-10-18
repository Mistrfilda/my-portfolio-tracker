<?php

declare(strict_types = 1);

namespace App\Goal;

use App\Admin\CurrentAppAdminGetter;
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
		private CurrentAppAdminGetter $currentAppAdminGetter,
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
			$this->currentAppAdminGetter->getAppAdmin(),
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

	public function createNewMonthGoal(PortfolioGoal $portfolioGoal): PortfolioGoal
	{
		$now = $this->datetimefactory->createNow();

		$newPortfolioGoal = new PortfolioGoal(
			$portfolioGoal->getAppAdmin(),
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

	public function createNewWeeklyGoal(PortfolioGoal $portfolioGoal): PortfolioGoal
	{
		$now = $this->datetimefactory->createNow();

		$newPortfolioGoal = new PortfolioGoal(
			$portfolioGoal->getAppAdmin(),
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
