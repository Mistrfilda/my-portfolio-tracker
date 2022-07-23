<?php

declare(strict_types = 1);

namespace App\UI\Control\Modal;

interface FrontModalControlFactory
{

	public function create(): FrontModalControl;

}
