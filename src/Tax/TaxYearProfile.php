<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxExpenseFlatRateEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('tax_year_profile')]
#[ORM\UniqueConstraint(name: 'tax_year_profile_owner_year_unique', columns: ['app_admin_id', 'year'])]
class TaxYearProfile implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::INTEGER)]
	private int $year;

	#[ORM\Column(type: Types::STRING, enumType: TaxExpenseFlatRateEnum::class)]
	private TaxExpenseFlatRateEnum $expenseFlatRate;

	#[ORM\Column(type: Types::INTEGER)]
	private int $activeMonthsHealth;

	#[ORM\Column(type: Types::INTEGER)]
	private int $activeMonthsSocial;

	#[ORM\Column(type: Types::STRING, enumType: TaxDividendModeEnum::class)]
	private TaxDividendModeEnum $dividendMode;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $includePreviousDecemberWorkIncome;

	#[ORM\Column(type: Types::INTEGER)]
	private int $manualBusinessIncomeAdjustmentCzk;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $manualBusinessIncomeAdjustmentNote;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $customForecastAnnualIncomeCzk;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $useCustomForecast;

	#[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
	private string|null $reserveTargetDate;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $expectedMonthlyHealthAdvanceCzk;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $expectedMonthlySocialAdvanceCzk;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $expectedRemainingIncomeTaxAdvancesCzk;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $confirmedIncomeTaxAdvancesCzk;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $confirmedHealthAdvancesCzk;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $confirmedSocialAdvancesCzk;

	#[ORM\Column(type: Types::INTEGER)]
	private int $pensionAndLifeDeductionCzk;

	#[ORM\Column(type: Types::INTEGER)]
	private int $mortgageInterestDeductionCzk;

	#[ORM\Column(type: Types::INTEGER)]
	private int $otherDeductionsCzk;

	#[ORM\Column(type: Types::INTEGER)]
	private int $otherTaxCreditsCzk;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $isClosed;

	/** @var ArrayCollection<int, TaxYearDonation> */
	#[ORM\OneToMany(targetEntity: TaxYearDonation::class, mappedBy: 'taxYearProfile', cascade: ['remove'])]
	private Collection $donations;

	public function __construct(
		AppAdmin $appAdmin,
		int $year,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->appAdmin = $appAdmin;
		$this->year = $year;
		$this->expenseFlatRate = TaxExpenseFlatRateEnum::RATE_60;
		$this->activeMonthsHealth = 12;
		$this->activeMonthsSocial = 12;
		$this->dividendMode = TaxDividendModeEnum::STANDARD_SECTION_8;
		$this->includePreviousDecemberWorkIncome = true;
		$this->manualBusinessIncomeAdjustmentCzk = 0;
		$this->manualBusinessIncomeAdjustmentNote = null;
		$this->customForecastAnnualIncomeCzk = null;
		$this->useCustomForecast = false;
		$this->reserveTargetDate = sprintf('%d-02-01', $year + 1);
		$this->expectedMonthlyHealthAdvanceCzk = null;
		$this->expectedMonthlySocialAdvanceCzk = null;
		$this->expectedRemainingIncomeTaxAdvancesCzk = null;
		$this->confirmedIncomeTaxAdvancesCzk = null;
		$this->confirmedHealthAdvancesCzk = null;
		$this->confirmedSocialAdvancesCzk = null;
		$this->pensionAndLifeDeductionCzk = 0;
		$this->mortgageInterestDeductionCzk = 0;
		$this->otherDeductionsCzk = 0;
		$this->otherTaxCreditsCzk = 0;
		$this->isClosed = false;
		$this->donations = new ArrayCollection();
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function updateSettings(
		TaxExpenseFlatRateEnum $expenseFlatRate,
		int $activeMonthsHealth,
		int $activeMonthsSocial,
		TaxDividendModeEnum $dividendMode,
		bool $includePreviousDecemberWorkIncome,
		int $manualBusinessIncomeAdjustmentCzk,
		string|null $manualBusinessIncomeAdjustmentNote,
		string|null $reserveTargetDate,
		int|null $expectedMonthlyHealthAdvanceCzk,
		int|null $expectedMonthlySocialAdvanceCzk,
		int|null $expectedRemainingIncomeTaxAdvancesCzk,
		int|null $confirmedIncomeTaxAdvancesCzk,
		int|null $confirmedHealthAdvancesCzk,
		int|null $confirmedSocialAdvancesCzk,
		int $pensionAndLifeDeductionCzk,
		int $mortgageInterestDeductionCzk,
		int $otherDeductionsCzk,
		int $otherTaxCreditsCzk,
		bool $isClosed,
		ImmutableDateTime $now,
	): void
	{
		$this->expenseFlatRate = $expenseFlatRate;
		$this->activeMonthsHealth = max(0, min(12, $activeMonthsHealth));
		$this->activeMonthsSocial = max(0, min(12, $activeMonthsSocial));
		$this->dividendMode = $dividendMode;
		$this->includePreviousDecemberWorkIncome = $includePreviousDecemberWorkIncome;
		$this->manualBusinessIncomeAdjustmentCzk = $manualBusinessIncomeAdjustmentCzk;
		$this->manualBusinessIncomeAdjustmentNote = $manualBusinessIncomeAdjustmentNote;
		$this->reserveTargetDate = $reserveTargetDate;
		$this->expectedMonthlyHealthAdvanceCzk = $expectedMonthlyHealthAdvanceCzk;
		$this->expectedMonthlySocialAdvanceCzk = $expectedMonthlySocialAdvanceCzk;
		$this->expectedRemainingIncomeTaxAdvancesCzk = $expectedRemainingIncomeTaxAdvancesCzk;
		$this->confirmedIncomeTaxAdvancesCzk = $confirmedIncomeTaxAdvancesCzk;
		$this->confirmedHealthAdvancesCzk = $confirmedHealthAdvancesCzk;
		$this->confirmedSocialAdvancesCzk = $confirmedSocialAdvancesCzk;
		$this->pensionAndLifeDeductionCzk = max(0, $pensionAndLifeDeductionCzk);
		$this->mortgageInterestDeductionCzk = max(0, $mortgageInterestDeductionCzk);
		$this->otherDeductionsCzk = max(0, $otherDeductionsCzk);
		$this->otherTaxCreditsCzk = max(0, $otherTaxCreditsCzk);
		$this->isClosed = $isClosed;
		$this->updatedAt = $now;
	}

	public function updateForecast(
		bool $useCustomForecast,
		int|null $customForecastAnnualIncomeCzk,
		ImmutableDateTime $now,
	): void
	{
		$this->useCustomForecast = $useCustomForecast;
		$this->customForecastAnnualIncomeCzk = $customForecastAnnualIncomeCzk !== null
			? max(0, $customForecastAnnualIncomeCzk)
			: null;
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

	public function getExpenseFlatRate(): TaxExpenseFlatRateEnum
	{
		return $this->expenseFlatRate;
	}

	public function getActiveMonthsHealth(): int
	{
		return $this->activeMonthsHealth;
	}

	public function getActiveMonthsSocial(): int
	{
		return $this->activeMonthsSocial;
	}

	public function getDividendMode(): TaxDividendModeEnum
	{
		return $this->dividendMode;
	}

	public function isIncludePreviousDecemberWorkIncome(): bool
	{
		return $this->includePreviousDecemberWorkIncome;
	}

	public function getManualBusinessIncomeAdjustmentCzk(): int
	{
		return $this->manualBusinessIncomeAdjustmentCzk;
	}

	public function getManualBusinessIncomeAdjustmentNote(): string|null
	{
		return $this->manualBusinessIncomeAdjustmentNote;
	}

	public function getCustomForecastAnnualIncomeCzk(): int|null
	{
		return $this->customForecastAnnualIncomeCzk;
	}

	public function isUseCustomForecast(): bool
	{
		return $this->useCustomForecast;
	}

	public function getReserveTargetDate(): string|null
	{
		return $this->reserveTargetDate;
	}

	public function getExpectedMonthlyHealthAdvanceCzk(): int|null
	{
		return $this->expectedMonthlyHealthAdvanceCzk;
	}

	public function getExpectedMonthlySocialAdvanceCzk(): int|null
	{
		return $this->expectedMonthlySocialAdvanceCzk;
	}

	public function getExpectedRemainingIncomeTaxAdvancesCzk(): int|null
	{
		return $this->expectedRemainingIncomeTaxAdvancesCzk;
	}

	public function getConfirmedIncomeTaxAdvancesCzk(): int|null
	{
		return $this->confirmedIncomeTaxAdvancesCzk;
	}

	public function getConfirmedHealthAdvancesCzk(): int|null
	{
		return $this->confirmedHealthAdvancesCzk;
	}

	public function getConfirmedSocialAdvancesCzk(): int|null
	{
		return $this->confirmedSocialAdvancesCzk;
	}

	public function getPensionAndLifeDeductionCzk(): int
	{
		return $this->pensionAndLifeDeductionCzk;
	}

	public function getMortgageInterestDeductionCzk(): int
	{
		return $this->mortgageInterestDeductionCzk;
	}

	public function getOtherDeductionsCzk(): int
	{
		return $this->otherDeductionsCzk;
	}

	public function getOtherTaxCreditsCzk(): int
	{
		return $this->otherTaxCreditsCzk;
	}

	public function isClosed(): bool
	{
		return $this->isClosed;
	}

	/**
	 * @return array<TaxYearDonation>
	 */
	public function getDonations(): array
	{
		return $this->donations->toArray();
	}

}
