<?php

declare(strict_types = 1);

namespace App\Work\Report\UI;

use App\UI\Base\BaseAdminPresenterTemplate;
use App\Work\Report\WorkReport;

class WorkReportTemplate extends BaseAdminPresenterTemplate
{

	public WorkReport $report;

	public WorkReportView $view;

}
