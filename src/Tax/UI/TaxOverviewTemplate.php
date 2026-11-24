<?php

declare(strict_types = 1);

namespace App\Tax\UI;

use App\Tax\DTO\TaxDonationItemDTO;
use App\Tax\DTO\TaxScenarioComparisonDTO;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use App\Tax\TaxYearDonation;
use App\Tax\TaxYearProfile;
use App\Tax\TaxYearReport;
use App\UI\Base\BaseAdminPresenterTemplate;

class TaxOverviewTemplate extends BaseAdminPresenterTemplate
{

	public int $selectedYear;

	/** @var list<int> */
	public array $availableYears;

	public TaxYearProfile $profile;

	/** @var array<TaxYearDonation> */
	public array $manualDonations;

	/** @var list<TaxDonationItemDTO> */
	public array $bankDonations;

	public int $bankDonationsTotalCzk;

	public TaxYearReport|null $latestReport;

	public bool $latestReportIsPending;

	public bool $latestReportCanRetry;

	public TaxYearReport|null $completedReport;

	public TaxYearReportSnapshotDTO|null $snapshot;

	public TaxScenarioComparisonDTO|null $scenarioComparison;

	public int|null $scenarioIncomeCzk;

	public int $donationMaxBasePercentage;

	public string|null $asOfDateFormatted;

	public string|null $reserveTargetDateFormatted;

}
