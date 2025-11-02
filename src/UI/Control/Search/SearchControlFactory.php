<?php

declare(strict_types = 1);

namespace App\UI\Control\Search;

interface SearchControlFactory
{

	public function create(): SearchControl;

}
