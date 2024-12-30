<?php

declare(strict_types = 1);

namespace App\System\Resolver;

use App\System\SystemValueEnum;
use App\System\SystemValuesDataBag;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class SystemValueCurrentVersionResolver implements SystemValueResolver
{

	public function __construct(
		private SystemValuesDataBag $systemValuesDataBag,
	)
	{
	}

	public function getValueForEnum(SystemValueEnum $systemValueEnum): string|int|ImmutableDateTime|null
	{
		$versionFile = $this->systemValuesDataBag->getParameter('versionFile');
		assert(is_string($versionFile));

		$fileContents = FileSystem::read($versionFile);
		$versions = Strings::split($fileContents, '~\n~');

		assert(array_key_exists(0, $versions));
		assert(array_key_exists(1, $versions));

		if ($systemValueEnum === SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION) {
			return TypeValidator::validateString($versions[0]);
		}

		return TypeValidator::validateString($versions[1]);
	}

}
