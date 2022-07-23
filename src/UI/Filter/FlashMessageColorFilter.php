<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\UI\FlashMessage\FlashMessageType;

class FlashMessageColorFilter
{

	public function format(string $type): string
	{
		return FlashMessageType::getColorForFlashMessage($type);
	}

}
