<?php

declare(strict_types = 1);

namespace App\System\Resolver;

use App\System\SystemValueEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface SystemValueResolver
{

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null;

}
