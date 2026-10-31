<?php

declare(strict_types = 1);

namespace App\Goal\Api;

use App\Goal\PortfolioGoal;
use const DATE_ATOM;

class PortfolioGoalSerializer
{

	/** @return array<string, mixed> */
	public function serialize(PortfolioGoal $goal): array
	{
		return [
			'id' => $goal->getId()->toString(),
			'type' => $goal->getType()->value,
			'currency' => $goal->getCurrency()->value,
			'startDate' => $goal->getStartDate()->format('Y-m-d'),
			'endDate' => $goal->getEndDate()->format('Y-m-d'),
			'repeatable' => $goal->getRepeatable()?->value,
			'valueAtStart' => $goal->getValueAtStart(),
			'currentValue' => $goal->getCurrentValue(),
			'targetValue' => $goal->getGoal(),
			'completionPercent' => $goal->getCompletionPercentage(),
			'remainingAmount' => $goal->getRemainingAmount(),
			'updatedAt' => $goal->getUpdatedAt()->format(DATE_ATOM),
		];
	}

}
