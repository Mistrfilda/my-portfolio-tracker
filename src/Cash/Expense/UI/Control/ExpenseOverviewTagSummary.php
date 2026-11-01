<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Tag\ExpenseTag;

class ExpenseOverviewTagSummary
{

	public function __construct(
		public readonly ExpenseTag $tag,
		public readonly SummaryPrice $summaryPrice,
	)
	{
	}

}
