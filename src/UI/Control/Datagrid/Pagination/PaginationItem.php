<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Pagination;

class PaginationItem
{

	public function __construct(
		private int $id,
		private string $label,
		private int $offset,
		private bool $disabled,
		private bool $isFirst,
		private bool $isLast,
		private bool $active,
	)
	{
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getOffset(): int
	{
		return $this->offset;
	}

	public function isDisabled(): bool
	{
		return $this->disabled;
	}

	public function isFirst(): bool
	{
		return $this->isFirst;
	}

	public function isLast(): bool
	{
		return $this->isLast;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

}
