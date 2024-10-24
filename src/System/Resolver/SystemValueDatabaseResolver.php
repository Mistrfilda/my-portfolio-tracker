<?php

declare(strict_types = 1);

namespace App\System\Resolver;

use App\System\SystemValueEnum;
use App\System\SystemValueRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class SystemValueDatabaseResolver implements SystemValueResolver
{

	public function __construct(
		private SystemValueRepository $systemValueRepository,
	)
	{

	}

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null
	{
		$systemValue = $this->systemValueRepository->findByEnum($systemValueEnum);
		if ($systemValue === null) {
			return null;
		}

		if ($systemValue->getStringValue() !== null) {
			return $systemValue->getStringValue();
		}

		if ($systemValue->getIntValue() !== null) {
			return $systemValue->getIntValue();
		}

		if ($systemValue->getDatetimeValue() !== null) {
			return $systemValue->getDatetimeValue();
		}

		return null;
	}

}
