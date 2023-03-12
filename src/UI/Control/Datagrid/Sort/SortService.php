<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Sort;

use Doctrine\Common\Collections\ArrayCollection;

class SortService
{

	/**
	 * @param array<string, string|null> $parameters
	 * @param ArrayCollection<string, Sort> $sorts
	 */
	public function getFiltersFromParameters(
		array $parameters,
		ArrayCollection $sorts,
		bool $fromHandle = false,
	): void
	{
		if ($fromHandle) {
			foreach ($sorts as $sort) {
				if (array_key_exists($sort->getColumn()->getColumn(), $parameters)) {
					continue;
				}

				if ($sort->hasDefaultParameter()) {
					$sort->resetSortDirection();
				}
			}
		}

		foreach ($parameters as $parameter => $direction) {
			$sort = $sorts->get($parameter);

			if ($sort === null) {
				throw new SortException(sprintf('Unknown parameter %s', $parameter));
			}

			if ($direction === null) {
				$sort->resetSortDirection();

				continue;
			}

			$sort->setSortDirection(SortDirectionEnum::from($direction));
		}
	}

	public function setCurrentSortDirectionForColumn(Sort $sort): Sort
	{
		match ($sort->getCurrentDirection()) {
			null => $sort->setSortDirection(SortDirectionEnum::DESC),
			SortDirectionEnum::DESC => $sort->setSortDirection(SortDirectionEnum::ASC),
			SortDirectionEnum::ASC => $sort->resetSortDirection()
		};

		return $sort;
	}

}
