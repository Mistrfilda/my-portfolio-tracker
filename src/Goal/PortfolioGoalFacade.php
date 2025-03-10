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
		private Datetimefactory $datetimefactory,
	)
	{
	}

	public function create(
		ImmutableDateTime $startDate,
		ImmutableDateTime $endDate,
		PortfolioGoalTypeEnum $type,
		float $goal,
	): void
	{
		$portfolioGoal = new PortfolioGoal(
			$startDate,
			$endDate,
			$type,
			$goal,
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
	): void
	{
		$portfolioGoal = $this->portfolioGoalRepository->getById($id);
		$portfolioGoal->update(
			$startDate,
			$endDate,
			$goal,
			$this->datetimefactory->createNow(),
		);
		$this->entityManager->flush();
	}

}
