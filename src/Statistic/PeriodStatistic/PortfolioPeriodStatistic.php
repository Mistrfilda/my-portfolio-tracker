<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticSummaryDTO;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('portfolio_period_statistic')]
#[ORM\Index(fields: ['createdAt'], name: 'portfolio_period_statistic_created_at_idx')]
class PortfolioPeriodStatistic implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $requestedStartAt;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $requestedEndAt;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $effectiveStartAt = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $effectiveEndAt = null;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioPeriodStatisticStatusEnum::class)]
	private PortfolioPeriodStatisticStatusEnum $status;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $summaryJson = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $assetSectionJson = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $dividendSectionJson = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $chartSectionJson = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processingStartedAt = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processingFinishedAt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $processingError = null;

	public function __construct(
		ImmutableDateTime $requestedStartAt,
		ImmutableDateTime $requestedEndAt,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->requestedStartAt = $requestedStartAt;
		$this->requestedEndAt = $requestedEndAt;
		$this->status = PortfolioPeriodStatisticStatusEnum::QUEUED;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function markProcessing(ImmutableDateTime $now): void
	{
		$this->status = PortfolioPeriodStatisticStatusEnum::PROCESSING;
		$this->processingStartedAt = $now;
		$this->processingFinishedAt = null;
		$this->processingError = null;
		$this->updatedAt = $now;
	}

	public function markQueued(ImmutableDateTime $now): void
	{
		$this->status = PortfolioPeriodStatisticStatusEnum::QUEUED;
		$this->processingStartedAt = null;
		$this->processingFinishedAt = null;
		$this->processingError = null;
		$this->updatedAt = $now;
	}

	public function complete(
		ImmutableDateTime $effectiveStartAt,
		ImmutableDateTime $effectiveEndAt,
		PortfolioPeriodStatisticSummaryDTO $summary,
		PortfolioPeriodStatisticAssetSectionDTO $assetSection,
		PortfolioPeriodStatisticDividendSectionDTO $dividendSection,
		PortfolioPeriodStatisticChartSectionDTO $chartSection,
		ImmutableDateTime $now,
	): void
	{
		$this->effectiveStartAt = $effectiveStartAt;
		$this->effectiveEndAt = $effectiveEndAt;
		$this->summaryJson = PortfolioPeriodStatisticJson::encode($summary);
		$this->assetSectionJson = PortfolioPeriodStatisticJson::encode($assetSection);
		$this->dividendSectionJson = PortfolioPeriodStatisticJson::encode($dividendSection);
		$this->chartSectionJson = PortfolioPeriodStatisticJson::encode($chartSection);
		$this->status = PortfolioPeriodStatisticStatusEnum::COMPLETED;
		$this->processingFinishedAt = $now;
		$this->processingError = null;
		$this->updatedAt = $now;
	}

	public function markFailed(string $error, ImmutableDateTime $now): void
	{
		$this->status = PortfolioPeriodStatisticStatusEnum::FAILED;
		$this->processingFinishedAt = $now;
		$this->processingError = $error;
		$this->updatedAt = $now;
	}

	public function getRequestedStartAt(): ImmutableDateTime
	{
		return $this->requestedStartAt;
	}

	public function getRequestedEndAt(): ImmutableDateTime
	{
		return $this->requestedEndAt;
	}

	public function getEffectiveStartAt(): ImmutableDateTime|null
	{
		return $this->effectiveStartAt;
	}

	public function getEffectiveEndAt(): ImmutableDateTime|null
	{
		return $this->effectiveEndAt;
	}

	public function getStatus(): PortfolioPeriodStatisticStatusEnum
	{
		return $this->status;
	}

	public function getStatusLabel(): string
	{
		return $this->status->format();
	}

	public function getProcessingStartedAt(): ImmutableDateTime|null
	{
		return $this->processingStartedAt;
	}

	public function getProcessingFinishedAt(): ImmutableDateTime|null
	{
		return $this->processingFinishedAt;
	}

	public function getProcessingError(): string|null
	{
		return $this->processingError;
	}

	public function isCompleted(): bool
	{
		return $this->status === PortfolioPeriodStatisticStatusEnum::COMPLETED;
	}

	public function isProcessing(): bool
	{
		return $this->status === PortfolioPeriodStatisticStatusEnum::PROCESSING;
	}

	public function canRetry(): bool
	{
		return $this->status === PortfolioPeriodStatisticStatusEnum::FAILED;
	}

	public function isPending(): bool
	{
		return in_array($this->status, [
			PortfolioPeriodStatisticStatusEnum::QUEUED,
			PortfolioPeriodStatisticStatusEnum::PROCESSING,
		], true);
	}

	public function getSummary(): PortfolioPeriodStatisticSummaryDTO|null
	{
		if ($this->summaryJson === null) {
			return null;
		}

		return PortfolioPeriodStatisticJson::decode(
			$this->summaryJson,
			PortfolioPeriodStatisticSummaryDTO::class,
		);
	}

	public function getAssetSection(): PortfolioPeriodStatisticAssetSectionDTO|null
	{
		if ($this->assetSectionJson === null) {
			return null;
		}

		return PortfolioPeriodStatisticJson::decode(
			$this->assetSectionJson,
			PortfolioPeriodStatisticAssetSectionDTO::class,
		);
	}

	public function getDividendSection(): PortfolioPeriodStatisticDividendSectionDTO|null
	{
		if ($this->dividendSectionJson === null) {
			return null;
		}

		return PortfolioPeriodStatisticJson::decode(
			$this->dividendSectionJson,
			PortfolioPeriodStatisticDividendSectionDTO::class,
		);
	}

	public function getChartSection(): PortfolioPeriodStatisticChartSectionDTO|null
	{
		if ($this->chartSectionJson === null) {
			return null;
		}

		return PortfolioPeriodStatisticJson::decode(
			$this->chartSectionJson,
			PortfolioPeriodStatisticChartSectionDTO::class,
		);
	}

}
