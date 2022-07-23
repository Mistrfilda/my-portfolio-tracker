<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Pagination;

class Pagination
{

	/**
	 * @param array<PaginationItem> $paginationItems
	 */
	public function __construct(private int $limit, private int $offset, private array $paginationItems)
	{
	}

	public function getLimit(): int
	{
		return $this->limit;
	}

	public function getOffset(): int
	{
		return $this->offset;
	}

	/**
	 * @return array<PaginationItem>
	 */
	public function getPaginationItems(): array
	{
		return $this->paginationItems;
	}

}
