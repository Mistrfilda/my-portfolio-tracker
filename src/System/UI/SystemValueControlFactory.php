<?php

declare(strict_types = 1);

namespace App\System\UI;

interface SystemValueControlFactory
{

	public function create(): SystemValueControl;

}
