<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Datasource;

use App\Doctrine\Entity;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Filter\IFilter;
use App\UI\Control\Datagrid\Sort\Sort;
use Doctrine\Common\Collections\ArrayCollection;
use Ramsey\Uuid\UuidInterface;

interface IDataSource
{

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 * @param ArrayCollection<string, Sort> $sorts
	 * @return array<string|int, Entity>
	 */
	public function getData(int $offset, int $limit, ArrayCollection $filters, ArrayCollection $sorts): array;

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 */
	public function getCount(ArrayCollection $filters): int;

	public function getValueForColumn(IColumn $column, Entity $row): string;

	public function getValueForKey(string $key, Entity $row): string|int|float|UuidInterface;

}
