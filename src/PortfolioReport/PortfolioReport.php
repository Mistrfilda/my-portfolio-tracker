<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_report')]
#[ORM\UniqueConstraint(name: 'portfolio_report_period_unique', columns: ['period_type', 'date_from', 'date_to'])]
class PortfolioReport implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioReportPeriodTypeEnum::class)]
	private PortfolioReportPeriodTypeEnum $periodType;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $dateFrom;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $dateTo;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioReportStatusEnum::class)]
	private PortfolioReportStatusEnum $status;

	#[ORM\Column(type: Types::FLOAT)]
	private float $portfolioValueStartCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $portfolioValueEndCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $portfolioValueDiffCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $portfolioValueDiffPercentage;

	#[ORM\Column(type: Types::FLOAT)]
	private float $investedAmountStartCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $investedAmountEndCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $investedAmountDiffCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $dividendsTotalCzk;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $goalsProgressSummary = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $summaryText = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $aiPrompt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $aiResponseRaw = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $aiSummary = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processingStartedAt = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $generatedAt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $errorMessage = null;

	#[ORM\ManyToOne(targetEntity: self::class)]
	private PortfolioReport|null $forceRegeneratedFrom = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $snapshot = null;

	/** @var Collection<int, PortfolioReportAssetPerformance> */
	#[ORM\OneToMany(
		targetEntity: PortfolioReportAssetPerformance::class,
		mappedBy: 'portfolioReport',
		cascade: ['persist', 'remove'],
		orphanRemoval: true,
	)]
	private Collection $assetPerformances;

	/** @var Collection<int, PortfolioReportDividend> */
	#[ORM\OneToMany(
		targetEntity: PortfolioReportDividend::class,
		mappedBy: 'portfolioReport',
		cascade: ['persist', 'remove'],
		orphanRemoval: true,
	)]
	private Collection $dividends;

	/** @var Collection<int, PortfolioReportGoalProgress> */
	#[ORM\OneToMany(
		targetEntity: PortfolioReportGoalProgress::class,
		mappedBy: 'portfolioReport',
		cascade: ['persist', 'remove'],
		orphanRemoval: true,
	)]
	private Collection $goalProgressItems;

	public function __construct(
		PortfolioReportPeriodTypeEnum $periodType,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
		ImmutableDateTime $now,
		PortfolioReport|null $forceRegeneratedFrom = null,
	)
	{
		$this->id = Uuid::uuid4();
		$this->periodType = $periodType;
		$this->dateFrom = $dateFrom;
		$this->dateTo = $dateTo;
		$this->status = PortfolioReportStatusEnum::PENDING;
		$this->portfolioValueStartCzk = 0;
		$this->portfolioValueEndCzk = 0;
		$this->portfolioValueDiffCzk = 0;
		$this->portfolioValueDiffPercentage = 0;
		$this->investedAmountStartCzk = 0;
		$this->investedAmountEndCzk = 0;
		$this->investedAmountDiffCzk = 0;
		$this->dividendsTotalCzk = 0;
		$this->forceRegeneratedFrom = $forceRegeneratedFrom;
		$this->assetPerformances = new ArrayCollection();
		$this->dividends = new ArrayCollection();
		$this->goalProgressItems = new ArrayCollection();
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	/**
	 * @param array<string, mixed>|null $snapshot
	 */
	public function markProcessing(ImmutableDateTime $now, array|null $snapshot = null): void
	{
		$this->status = PortfolioReportStatusEnum::PROCESSING;
		$this->processingStartedAt = $now;
		$this->errorMessage = null;
		$this->setSnapshot($snapshot);
		$this->updatedAt = $now;
	}

	/**
	 * @param array<int, PortfolioReportAssetPerformance> $assetPerformances
	 * @param array<int, PortfolioReportDividend> $dividends
	 * @param array<int, PortfolioReportGoalProgress> $goalProgressItems
	 * @param array<string, mixed>|null $snapshot
	 */
	public function complete(
		float $portfolioValueStartCzk,
		float $portfolioValueEndCzk,
		float $investedAmountStartCzk,
		float $investedAmountEndCzk,
		float $dividendsTotalCzk,
		string|null $goalsProgressSummary,
		string|null $summaryText,
		string|null $aiPrompt,
		array $assetPerformances,
		array $dividends,
		array $goalProgressItems,
		ImmutableDateTime $now,
		array|null $snapshot = null,
	): void
	{
		$this->status = PortfolioReportStatusEnum::DONE;
		$this->portfolioValueStartCzk = $portfolioValueStartCzk;
		$this->portfolioValueEndCzk = $portfolioValueEndCzk;
		$this->portfolioValueDiffCzk = $portfolioValueEndCzk - $portfolioValueStartCzk;
		$this->portfolioValueDiffPercentage = $portfolioValueStartCzk === 0.0
			? 0.0
			: ($portfolioValueEndCzk - $portfolioValueStartCzk) / $portfolioValueStartCzk * 100;
		$this->investedAmountStartCzk = $investedAmountStartCzk;
		$this->investedAmountEndCzk = $investedAmountEndCzk;
		$this->investedAmountDiffCzk = $investedAmountEndCzk - $investedAmountStartCzk;
		$this->dividendsTotalCzk = $dividendsTotalCzk;
		$this->goalsProgressSummary = $goalsProgressSummary;
		$this->summaryText = $summaryText;
		$this->aiPrompt = $aiPrompt;
		$this->generatedAt = $now;
		$this->errorMessage = null;
		$this->replaceAssetPerformances($assetPerformances);
		$this->replaceDividends($dividends);
		$this->replaceGoalProgressItems($goalProgressItems);
		$this->setSnapshot($snapshot);
		$this->updatedAt = $now;
	}

	public function fail(string $errorMessage, ImmutableDateTime $now): void
	{
		$this->status = PortfolioReportStatusEnum::FAILED;
		$this->errorMessage = $errorMessage;
		$this->updatedAt = $now;
	}

	public function setAiResponse(string $aiResponseRaw, string|null $aiSummary, ImmutableDateTime $now): void
	{
		$this->aiResponseRaw = $aiResponseRaw;
		$this->aiSummary = $aiSummary;
		$this->updatedAt = $now;
	}

	public function getPeriodType(): PortfolioReportPeriodTypeEnum
	{
		return $this->periodType;
	}

	public function getDateFrom(): ImmutableDateTime
	{
		return $this->dateFrom;
	}

	public function getDateTo(): ImmutableDateTime
	{
		return $this->dateTo;
	}

	public function getStatus(): PortfolioReportStatusEnum
	{
		return $this->status;
	}

	public function getPortfolioValueStartCzk(): float
	{
		return $this->portfolioValueStartCzk;
	}

	public function getPortfolioValueEndCzk(): float
	{
		return $this->portfolioValueEndCzk;
	}

	public function getPortfolioValueDiffCzk(): float
	{
		return $this->portfolioValueDiffCzk;
	}

	public function getPortfolioValueDiffPercentage(): float
	{
		return $this->portfolioValueDiffPercentage;
	}

	public function getInvestedAmountStartCzk(): float
	{
		return $this->investedAmountStartCzk;
	}

	public function getInvestedAmountEndCzk(): float
	{
		return $this->investedAmountEndCzk;
	}

	public function getInvestedAmountDiffCzk(): float
	{
		return $this->investedAmountDiffCzk;
	}

	public function getDividendsTotalCzk(): float
	{
		return $this->dividendsTotalCzk;
	}

	public function getGoalsProgressSummary(): string|null
	{
		return $this->goalsProgressSummary;
	}

	public function getSummaryText(): string|null
	{
		return $this->summaryText;
	}

	public function getAiPrompt(): string|null
	{
		return $this->aiPrompt;
	}

	public function getAiResponseRaw(): string|null
	{
		return $this->aiResponseRaw;
	}

	public function getAiSummary(): string|null
	{
		return $this->aiSummary;
	}

	public function getProcessingStartedAt(): ImmutableDateTime|null
	{
		return $this->processingStartedAt;
	}

	public function getGeneratedAt(): ImmutableDateTime|null
	{
		return $this->generatedAt;
	}

	public function getErrorMessage(): string|null
	{
		return $this->errorMessage;
	}

	public function getForceRegeneratedFrom(): PortfolioReport|null
	{
		return $this->forceRegeneratedFrom;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getSnapshot(): array|null
	{
		if ($this->snapshot === null) {
			return null;
		}

		/** @var array<string, mixed> $snapshot */
		$snapshot = Json::decode($this->snapshot, true);

		return $snapshot;
	}

	/** @return Collection<int, PortfolioReportAssetPerformance> */
	public function getAssetPerformances(): Collection
	{
		return $this->assetPerformances;
	}

	/** @return Collection<int, PortfolioReportDividend> */
	public function getDividends(): Collection
	{
		return $this->dividends;
	}

	/** @return Collection<int, PortfolioReportGoalProgress> */
	public function getGoalProgressItems(): Collection
	{
		return $this->goalProgressItems;
	}

	/**
	 * @param array<int, PortfolioReportAssetPerformance> $assetPerformances
	 */
	private function replaceAssetPerformances(array $assetPerformances): void
	{
		$this->assetPerformances = new ArrayCollection($assetPerformances);
	}

	/**
	 * @param array<int, PortfolioReportDividend> $dividends
	 */
	private function replaceDividends(array $dividends): void
	{
		$this->dividends = new ArrayCollection($dividends);
	}

	/**
	 * @param array<int, PortfolioReportGoalProgress> $goalProgressItems
	 */
	private function replaceGoalProgressItems(array $goalProgressItems): void
	{
		$this->goalProgressItems = new ArrayCollection($goalProgressItems);
	}

	/**
	 * @param array<string, mixed>|null $snapshot
	 */
	private function setSnapshot(array|null $snapshot): void
	{
		$this->snapshot = $snapshot === null ? null : Json::encode($snapshot);
	}

}
