<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_report_goal_progress')]
class PortfolioReportGoalProgress implements Entity
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: PortfolioReport::class, inversedBy: 'goalProgressItems')]
	#[ORM\JoinColumn(nullable: false)]
	private PortfolioReport $portfolioReport;

	#[ORM\ManyToOne(targetEntity: PortfolioGoal::class)]
	private PortfolioGoal|null $portfolioGoal;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioGoalTypeEnum::class)]
	private PortfolioGoalTypeEnum $goalType;

	#[ORM\Column(type: Types::FLOAT)]
	private float $goalStartValue;

	#[ORM\Column(type: Types::FLOAT)]
	private float $goalEndValue;

	#[ORM\Column(type: Types::FLOAT)]
	private float $goalTargetValue;

	#[ORM\Column(type: Types::FLOAT)]
	private float $completionPercentageStart;

	#[ORM\Column(type: Types::FLOAT)]
	private float $completionPercentageEnd;

	#[ORM\Column(type: Types::FLOAT)]
	private float $completionPercentageDiff;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $summary = null;

	public function __construct(
		PortfolioReport $portfolioReport,
		PortfolioGoal|null $portfolioGoal,
		PortfolioGoalTypeEnum $goalType,
		float $goalStartValue,
		float $goalEndValue,
		float $goalTargetValue,
		string|null $summary,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->portfolioReport = $portfolioReport;
		$this->portfolioGoal = $portfolioGoal;
		$this->goalType = $goalType;
		$this->goalStartValue = $goalStartValue;
		$this->goalEndValue = $goalEndValue;
		$this->goalTargetValue = $goalTargetValue;
		$this->completionPercentageStart = $this->calculateCompletionPercentage($goalStartValue, $goalTargetValue);
		$this->completionPercentageEnd = $this->calculateCompletionPercentage($goalEndValue, $goalTargetValue);
		$this->completionPercentageDiff = $this->completionPercentageEnd - $this->completionPercentageStart;
		$this->summary = $summary;
		$this->createdAt = $now;
	}

	public function getPortfolioReport(): PortfolioReport
	{
		return $this->portfolioReport;
	}

	public function getPortfolioGoal(): PortfolioGoal|null
	{
		return $this->portfolioGoal;
	}

	public function getGoalType(): PortfolioGoalTypeEnum
	{
		return $this->goalType;
	}

	public function getGoalStartValue(): float
	{
		return $this->goalStartValue;
	}

	public function getGoalEndValue(): float
	{
		return $this->goalEndValue;
	}

	public function getGoalTargetValue(): float
	{
		return $this->goalTargetValue;
	}

	public function getCompletionPercentageStart(): float
	{
		return $this->completionPercentageStart;
	}

	public function getCompletionPercentageEnd(): float
	{
		return $this->completionPercentageEnd;
	}

	public function getCompletionPercentageDiff(): float
	{
		return $this->completionPercentageDiff;
	}

	public function getSummary(): string|null
	{
		return $this->summary;
	}

	private function calculateCompletionPercentage(float $currentValue, float $goalTargetValue): float
	{
		if ($goalTargetValue === 0.0) {
			return 0.0;
		}

		return $currentValue / $goalTargetValue * 100;
	}

}
