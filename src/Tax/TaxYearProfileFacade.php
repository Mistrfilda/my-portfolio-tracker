<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxExpenseFlatRateEnum;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class TaxYearProfileFacade
{

	public function __construct(
		private TaxYearProfileRepository $profileRepository,
		private TaxYearDonationRepository $donationRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function getOrCreate(int $year, AppAdmin|null $appAdmin = null): TaxYearProfile
	{
		$owner = $appAdmin ?? $this->currentAppAdminGetter->getAppAdmin();
		$existing = $this->profileRepository->findByYear($year, $owner);
		if ($existing !== null) {
			return $existing;
		}

		$profile = new TaxYearProfile($owner, $year, $this->datetimeFactory->createNow());
		$this->entityManager->persist($profile);
		$this->entityManager->flush();

		return $profile;
	}

	public function updateSettings(
		int $year,
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
	): TaxYearProfile
	{
		$profile = $this->getOrCreate($year);
		$profile->updateSettings(
			expenseFlatRate: $expenseFlatRate,
			activeMonthsHealth: $activeMonthsHealth,
			activeMonthsSocial: $activeMonthsSocial,
			dividendMode: $dividendMode,
			includePreviousDecemberWorkIncome: $includePreviousDecemberWorkIncome,
			manualBusinessIncomeAdjustmentCzk: $manualBusinessIncomeAdjustmentCzk,
			manualBusinessIncomeAdjustmentNote: $manualBusinessIncomeAdjustmentNote,
			reserveTargetDate: $reserveTargetDate,
			expectedMonthlyHealthAdvanceCzk: $expectedMonthlyHealthAdvanceCzk,
			expectedMonthlySocialAdvanceCzk: $expectedMonthlySocialAdvanceCzk,
			expectedRemainingIncomeTaxAdvancesCzk: $expectedRemainingIncomeTaxAdvancesCzk,
			confirmedIncomeTaxAdvancesCzk: $confirmedIncomeTaxAdvancesCzk,
			confirmedHealthAdvancesCzk: $confirmedHealthAdvancesCzk,
			confirmedSocialAdvancesCzk: $confirmedSocialAdvancesCzk,
			pensionAndLifeDeductionCzk: $pensionAndLifeDeductionCzk,
			mortgageInterestDeductionCzk: $mortgageInterestDeductionCzk,
			otherDeductionsCzk: $otherDeductionsCzk,
			otherTaxCreditsCzk: $otherTaxCreditsCzk,
			isClosed: $isClosed,
			now: $this->datetimeFactory->createNow(),
		);
		$this->entityManager->flush();

		return $profile;
	}

	public function updateForecast(
		int $year,
		bool $useCustomForecast,
		int|null $customForecastAnnualIncomeCzk,
	): TaxYearProfile
	{
		$profile = $this->getOrCreate($year);
		$profile->updateForecast(
			$useCustomForecast,
			$customForecastAnnualIncomeCzk,
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->flush();

		return $profile;
	}

	public function addDonation(
		int $year,
		string $recipientName,
		int $amountCzk,
		ImmutableDateTime|null $donatedAt,
		string|null $note,
	): TaxYearDonation
	{
		if ($donatedAt !== null && $donatedAt->getYear() !== $year) {
			throw new InvalidArgumentException(sprintf(
				'Donation date year %d does not match tax year %d.',
				$donatedAt->getYear(),
				$year,
			));
		}

		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$profile = $this->getOrCreate($year, $owner);
		$donation = new TaxYearDonation(
			$owner,
			$profile,
			trim($recipientName),
			$amountCzk,
			$donatedAt,
			$note !== null && trim($note) !== '' ? trim($note) : null,
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->persist($donation);
		$this->entityManager->flush();

		return $donation;
	}

	public function deleteDonation(string $donationId): void
	{
		$donation = $this->donationRepository->getById(Uuid::fromString($donationId));
		$this->entityManager->remove($donation);
		$this->entityManager->flush();
	}

}
