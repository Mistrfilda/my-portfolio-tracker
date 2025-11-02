<?php

declare(strict_types = 1);

namespace App\UI\Control\Search;

class SearchGroup
{

	/**
	 * @param array<SearchGroupItem> $items
	 */
	public function __construct(private string $name, private array $items)
	{

	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return array<SearchGroupItem>
	 */
	public function getItems(): array
	{
		return $this->items;
	}

}
