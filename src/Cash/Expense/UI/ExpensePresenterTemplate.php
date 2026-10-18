<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\UI\Base\BaseAdminPresenterTemplate;

class ExpensePresenterTemplate extends BaseAdminPresenterTemplate
{

	public bool $showModal = false;

	public string $lastProcessedAt;

}
