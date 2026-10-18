<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome;

use App\Admin\AppAdmin;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Utils\Datetime\DatetimeConst;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('work_monthly_income')]
#[ORM\UniqueConstraint(name: 'work_income_owner_period_unique', columns: ['app_admin_id', 'year', 'month'])]
class WorkMonthlyIncome implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::INTEGER)]
	private int $year;

	#[ORM\Column(type: Types::INTEGER)]
	private int $month;

	#[ORM\Column(type: Types::FLOAT)]
	private float $hours;

	#[ORM\Column(type: Types::INTEGER)]
	private int $hourlyRate;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currencyEnum;

	public function __construct(
		AppAdmin $appAdmin,
		int $year,
		int $month,
		float $hours,
		int $hourlyRate,
		ImmutableDateTime $now,
	)
	{
		$this->appAdmin = $appAdmin;
		$this->id = Uuid::uuid4();
		$this->year = $year;
		$this->month = $month;
		$this->hours = $hours;
		$this->hourlyRate = $hourlyRate;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->currencyEnum = CurrencyEnum::CZK;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function update(float $hours, ImmutableDateTime $now, int|null $hourlyRate = null): void
	{
		$this->hours = $hours;
		$this->updatedAt = $now;
	}

	public function getFormatedName(): string
	{
		return DatetimeConst::CZECH_MONTHS[$this->month] . ' ' . $this->year;
	}

	public function getYear(): int
	{
		return $this->year;
	}

	public function getMonth(): int
	{
		return $this->month;
	}

	public function getHours(): float
	{
		return $this->hours;
	}

	public function getHourlyRate(): int
	{
		return $this->hourlyRate;
	}

	public function getSummaryPrice(): SummaryPrice
	{
		return new SummaryPrice(
			$this->currencyEnum,
			$this->hours * $this->hourlyRate,
			(int) $this->hours,
		);
	}

	public function getCurrencyEnum(): CurrencyEnum
	{
		return $this->currencyEnum;
	}

}
