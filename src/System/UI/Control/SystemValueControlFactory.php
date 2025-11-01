<?php

declare(strict_types = 1);

namespace App\System\UI\Control;

interface SystemValueControlFactory
{

	public function create(): SystemValueControl;

}
