<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Tag\ExpenseTag;
use App\UI\Base\BaseControlTemplate;

class ExpenseOverviewCategoryControlTemplate extends BaseControlTemplate
{

	public SummaryPrice $totalSummaryPrice;

	public SummaryPrice $investmentSummaryPrice;

	public SummaryPrice $excludedInvestmentSummaryPrice;

	public SummaryPrice $totalIncomeSummaryPrice;

	public SummaryPrice $totalWorkIncome;

	public SummaryPrice $totalDividendIncome;

	public SummaryPrice $selectedSummaryPrice;

	/** @var array<ExpanseOverviewData> */
	public array $data;

	/** @var array<ExpenseOverviewBankAccountSummary> */
	public array $bankAccountSummaryPrices;

	/** @var array<ExpenseOverviewTagSummary> */
	public array $otherTagSummaries;

	public ExpenseTag|null $selectedOtherTag;

}
