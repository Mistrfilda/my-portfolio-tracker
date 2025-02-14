<?php

declare(strict_types = 1);

namespace App\Goal;

use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('portfolio_goal')]
class PortfolioGoal implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $startDate;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $endDate;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioGoalTypeEnum::class)]
	private PortfolioGoalTypeEnum $type;

	#[ORM\Column(type: Types::FLOAT)]
	private float $valueAtStart;

	#[ORM\Column(type: Types::FLOAT)]
	private float $currentValue;

	#[ORM\Column(type: Types::FLOAT)]
	private float $valueAtEnd;

	#[ORM\Column(type: Types::FLOAT)]
	private float $goal;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $active;

	/** @var array<string, float> */
	#[ORM\Column(type: Types::JSON)]
	private array $statistics;

	public function __construct(
		ImmutableDateTime $startDate,
		ImmutableDateTime $endDate,
		PortfolioGoalTypeEnum $type,
		float $goal,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->startDate = $startDate;
		$this->endDate = $endDate;
		$this->type = $type;
		$this->goal = $goal;
		$this->active = false;
		$this->valueAtStart = 0;
		$this->currentValue = 0;
		$this->valueAtEnd = 0;
		$this->statistics = [];
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		ImmutableDateTime $startDate,
		ImmutableDateTime $endDate,
		float $goal,
		ImmutableDateTime $now,
	): void
	{
		$this->startDate = $startDate;
		$this->endDate = $endDate;
		$this->goal = $goal;
		$this->updatedAt = $now;
	}

	public function start(
		float $valueAtStart,
		float $currentValue,
		ImmutableDateTime $now,
	): void
	{
		$this->valueAtStart = $valueAtStart;
		$this->currentValue = $currentValue;
		$this->valueAtEnd = $currentValue;
		$this->statistics[$now->format('Y-m-d')] = $currentValue;
		$this->active = true;
		$this->updatedAt = $now;
	}

	public function end(float $currentValue, ImmutableDateTime $now): void
	{
		$this->currentValue = $currentValue;
		$this->valueAtEnd = $currentValue;
		$this->statistics[$now->format('Y-m-d')] = $currentValue;
		$this->active = false;
		$this->updatedAt = $now;
	}

	public function updateCurrentValue(float $currentValue, ImmutableDateTime $now): void
	{
		$this->currentValue = $currentValue;
		$this->valueAtEnd = $currentValue;
		$this->statistics[$now->format('Y-m-d')] = $currentValue;
		$this->updatedAt = $now;
	}

	public function getCompletionPercentage(): float
	{
		return ($this->currentValue - $this->valueAtStart) / ($this->goal - $this->valueAtStart) * 100;
	}

	public function getRemainingAmount(): float
	{
		return $this->goal - $this->currentValue;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->type->getCurrency();
	}

	public function getRemainingDays(ImmutableDateTime $now): int
	{
		$remainingDays = $this->endDate->diff($now)->days;
		if ($remainingDays === false) {
			return 0;
		}

		return $remainingDays;
	}

	/**
	 * @return array<string, float>
	 */
	public function getStatistics(): array
	{
		return $this->statistics;
	}

	public function getStartDate(): ImmutableDateTime
	{
		return $this->startDate;
	}

	public function getEndDate(): ImmutableDateTime
	{
		return $this->endDate;
	}

	public function getType(): PortfolioGoalTypeEnum
	{
		return $this->type;
	}

	public function getValueAtStart(): float
	{
		return $this->valueAtStart;
	}

	public function getCurrentValue(): float
	{
		return $this->currentValue;
	}

	public function getValueAtEnd(): float
	{
		return $this->valueAtEnd;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

	public function getGoal(): float
	{
		return $this->goal;
	}

	public function setGoal(float $goal): void
	{
		$this->goal = $goal;
	}

}
