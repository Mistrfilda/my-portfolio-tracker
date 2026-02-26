<?php

declare(strict_types = 1);

namespace App\Goal;

use App\Goal\Resolver\PortfolioGoalResolver;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationTypeEnum;
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
		private PortfolioGoalFacade $portfolioGoalFacade,
		private NotificationFacade $notificationFacade,
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

				$this->checkGoalNotification($portfolioGoal);

				$this->entityManager->flush();
			}
		}

		$now = $this->datetimeFactory->createNow();
		if ($portfolioGoal->getEndDate() < $now) {
			$portfolioGoal->endWithCurrentValue($now);
			$this->entityManager->flush();

			$newPortfolioGoal = null;
			if ($portfolioGoal->getRepeatable() === PortfolioGoalRepeatableEnum::MONTHLY) {
				$newPortfolioGoal = $this->portfolioGoalFacade->createNewMonthGoal($id);
			}

			if ($portfolioGoal->getRepeatable() === PortfolioGoalRepeatableEnum::WEEKLY) {
				$newPortfolioGoal = $this->portfolioGoalFacade->createNewWeeklyGoal($id);
			}

			if ($newPortfolioGoal !== null) {
				$this->updateGoal($newPortfolioGoal->getId());
			}
		}
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

	private function checkGoalNotification(PortfolioGoal $portfolioGoal): void
	{
		$currentPercentage = $portfolioGoal->getCompletionPercentage();
		$lastNotifiedPercentage = $portfolioGoal->getLastNotifiedPercentage();

		if ($lastNotifiedPercentage === null) {
			$portfolioGoal->setLastNotifiedPercentage($currentPercentage);
			return;
		}

		$threshold = $portfolioGoal->getRepeatable() === null ? 1 : 3;

		$currentStep = floor($currentPercentage / $threshold);
		$lastStep = floor($lastNotifiedPercentage / $threshold);

		if ($currentStep > $lastStep) {
			$this->notificationFacade->create(
				NotificationTypeEnum::GOALS_UPDATES,
				[NotificationChannelEnum::DISCORD],
				sprintf(
					"**Milník cíle portfolia dosažen!**\nCíl: **%s**\nAktuální progress: **%s %%**",
					$portfolioGoal->getType()->format(),
					round($currentPercentage, 2),
				),
			);

			$portfolioGoal->setLastNotifiedPercentage($currentPercentage);
		}
	}

}
