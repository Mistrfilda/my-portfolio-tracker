<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('tax_year_report')]
#[ORM\Index(fields: ['appAdmin', 'year', 'createdAt'], name: 'tax_year_report_owner_year_created_idx')]
class TaxYearReport implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	public const int PROCESSING_TIMEOUT_MINUTES = 10;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::INTEGER)]
	private int $year;

	#[ORM\Column(type: Types::STRING, enumType: TaxYearReportStatusEnum::class)]
	private TaxYearReportStatusEnum $status;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $asOfDate;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $snapshotJson = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processingStartedAt = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processingFinishedAt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $processingError = null;

	public function __construct(
		AppAdmin $appAdmin,
		int $year,
		ImmutableDateTime $asOfDate,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->appAdmin = $appAdmin;
		$this->year = $year;
		$this->asOfDate = $asOfDate;
		$this->status = TaxYearReportStatusEnum::QUEUED;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function getYear(): int
	{
		return $this->year;
	}

	public function getAsOfDate(): ImmutableDateTime
	{
		return $this->asOfDate;
	}

	public function getStatus(): TaxYearReportStatusEnum
	{
		return $this->status;
	}

	public function getStatusLabel(): string
	{
		return $this->status->format();
	}

	public function markProcessing(ImmutableDateTime $now): void
	{
		$this->status = TaxYearReportStatusEnum::PROCESSING;
		$this->processingStartedAt = $now;
		$this->processingFinishedAt = null;
		$this->processingError = null;
		$this->updatedAt = $now;
	}

	public function markQueued(ImmutableDateTime $now): void
	{
		$this->status = TaxYearReportStatusEnum::QUEUED;
		$this->processingStartedAt = null;
		$this->processingFinishedAt = null;
		$this->processingError = null;
		$this->updatedAt = $now;
	}

	public function complete(TaxYearReportSnapshotDTO $snapshot, ImmutableDateTime $now): void
	{
		$this->snapshotJson = TaxYearReportJson::encode($snapshot);
		$this->status = TaxYearReportStatusEnum::COMPLETED;
		$this->processingFinishedAt = $now;
		$this->processingError = null;
		$this->updatedAt = $now;
	}

	public function markFailed(string $error, ImmutableDateTime $now): void
	{
		$this->status = TaxYearReportStatusEnum::FAILED;
		$this->processingFinishedAt = $now;
		$this->processingError = $error;
		$this->updatedAt = $now;
	}

	public function isCompleted(): bool
	{
		return $this->status === TaxYearReportStatusEnum::COMPLETED;
	}

	public function isProcessing(): bool
	{
		return $this->status === TaxYearReportStatusEnum::PROCESSING;
	}

	public function isStaleProcessing(ImmutableDateTime $now): bool
	{
		if ($this->status !== TaxYearReportStatusEnum::PROCESSING || $this->processingStartedAt === null) {
			return false;
		}

		return $now->getTimestamp() - $this->processingStartedAt->getTimestamp() >= self::PROCESSING_TIMEOUT_MINUTES * 60;
	}

	public function isPending(ImmutableDateTime|null $now = null): bool
	{
		if ($this->status === TaxYearReportStatusEnum::QUEUED) {
			return true;
		}

		if ($this->status !== TaxYearReportStatusEnum::PROCESSING) {
			return false;
		}

		return $now === null || !$this->isStaleProcessing($now);
	}

	public function canRetry(ImmutableDateTime|null $now = null): bool
	{
		if ($this->status === TaxYearReportStatusEnum::FAILED) {
			return true;
		}

		return $now !== null && $this->isStaleProcessing($now);
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

	public function getSnapshot(): TaxYearReportSnapshotDTO|null
	{
		if ($this->snapshotJson === null) {
			return null;
		}

		return TaxYearReportJson::decode($this->snapshotJson, TaxYearReportSnapshotDTO::class);
	}

}
