<?php

declare(strict_types = 1);

namespace App\Goal;

use App\Goal\Resolver\PortfolioGoalResolver;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class PortfolioGoalUpdateFacade
{

	/**
	 * @param array<PortfolioGoalResolver> $resolvers
	 */
	public function __construct(
		private array $resolvers,
		private PortfolioGoalRepository $portfolioGoalRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function updateAllActive(): void
	{
		foreach ($this->portfolioGoalRepository->findActive($this->datetimeFactory->createNow()) as $portfolioGoal) {
			$this->updateGoal($portfolioGoal->getId());
		}
	}

	public function updateGoal(UuidInterface $id): void
	{
		$portfolioGoal = $this->portfolioGoalRepository->getById($id);
		foreach ($this->resolvers as $resolver) {
			if ($resolver->canResolveType($portfolioGoal)) {
				$value = $resolver->resolve($portfolioGoal);
				$portfolioGoal->updateCurrentValue(
					$value,
					$this->datetimeFactory->createNow(),
				);
			}
		}

		$this->entityManager->flush();
	}

	public function startGoal(UuidInterface $id): void
	{
		$portfolioGoal = $this->portfolioGoalRepository->getById($id);
		foreach ($this->resolvers as $resolver) {
			if ($resolver->canResolveType($portfolioGoal)) {
				$value = $resolver->resolve($portfolioGoal);
				$portfolioGoal->start(
					$value,
					$value,
					$this->datetimeFactory->createNow(),
				);
			}
		}

		$this->entityManager->flush();
	}

	public function endGoal(UuidInterface $id): void
	{
		$portfolioGoal = $this->portfolioGoalRepository->getById($id);
		foreach ($this->resolvers as $resolver) {
			if ($resolver->canResolveType($portfolioGoal)) {
				$value = $resolver->resolve($portfolioGoal);
				$portfolioGoal->end(
					$value,
					$this->datetimeFactory->createNow(),
				);
			}
		}

		$this->entityManager->flush();
	}

}
