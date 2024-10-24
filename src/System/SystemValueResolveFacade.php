<?php

declare(strict_types = 1);

namespace App\System;

use App\System\Resolver\SystemValueResolver;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Filter\DatetimeFormatFilter;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class SystemValueResolveFacade
{

	//phpcs:disable
	/**
	 * @param SystemValueResolver[] $resolvers
	 */
	public function __construct(private array $resolvers)
	{
	}
	//phpcs:enable

	/**
	 * @return array<string, string>
	 */
	public function getAllValues(): array
	{
		$values = [];
		foreach (SystemValueEnum::cases() as $case) {
			foreach ($this->resolvers as $resolver) {
				if ($case->getResolverClass() === $resolver::class) {
					$values[$case->getLabel()] = $this->formatValue($resolver->getValueForEnum($case));
				}
			}
		}

		return $values;
	}

	private function formatValue(string|int|ImmutableDateTime|null $value): string
	{
		if ($value === null) {
			return Datagrid::NULLABLE_PLACEHOLDER;
		}

		if ($value instanceof ImmutableDateTime) {
			return DatetimeFormatFilter::formatValue($value);
		}

		return (string) $value;
	}

}
