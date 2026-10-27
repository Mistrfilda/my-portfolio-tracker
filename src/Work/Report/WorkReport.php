<?php

declare(strict_types = 1);

namespace App\Work\Report;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

/** @phpstan-import-type WorkReportData from WorkReportBuilder */
#[ORM\Entity]
#[ORM\Table('work_report')]
#[ORM\UniqueConstraint(name: 'work_report_owner_date_unique', fields: ['appAdmin', 'reportDate'])]
class WorkReport implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ImmutableDateTime $reportDate;

	/** @var WorkReportData */
	#[ORM\Column(type: Types::JSON)]
	private array $data;

	/** @param WorkReportData $data */
	public function __construct(AppAdmin $appAdmin, ImmutableDateTime $reportDate, array $data, ImmutableDateTime $now)
	{
		$this->id = Uuid::uuid4();
		$this->appAdmin = $appAdmin;
		$this->reportDate = $reportDate;
		$this->data = $data;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function getReportDate(): ImmutableDateTime
	{
		return $this->reportDate;
	}

	/** @return WorkReportData */
	public function getData(): array
	{
		return $this->data;
	}

	/** @param WorkReportData $data */
	public function update(array $data, ImmutableDateTime $now): void
	{
		$this->data = $data;
		$this->updatedAt = $now;
	}

}
