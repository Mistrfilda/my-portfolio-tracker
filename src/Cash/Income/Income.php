<?php

declare(strict_types = 1);

namespace App\Cash\Income;

use App\Cash\Utils\CashPrice;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface Income
{

	public function getDate(): ImmutableDateTime;

	public function getExpensePrice(): CashPrice;

	public function getIdentifier(): string;

}
