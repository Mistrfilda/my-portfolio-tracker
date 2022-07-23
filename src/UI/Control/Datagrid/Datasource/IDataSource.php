<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Datasource;

use App\Doctrine\IEntity;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Filter\IFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Ramsey\Uuid\UuidInterface;

interface IDataSource
{

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 * @return array<string|int, IEntity>
	 */
	public function getData(int $offset, int $limit, ArrayCollection $filters): array;

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 */
	public function getCount(ArrayCollection $filters): int;

	public function getValueForColumn(IColumn $column, IEntity $row): string;

	public function getValueForKey(string $key, IEntity $row): string|int|float|UuidInterface;

}
