<?php

declare(strict_types = 1);

namespace App\UI\Menu;

use App\UI\Icon\SvgIcon;

class MenuGroup
{

	/**
	 * @param array<MenuItem> $items
	 */
	public function __construct(
		private string $label,
		private SvgIcon|null $icon,
		private array $items,
		private bool $defaultOpen = false,
	)
	{
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getIcon(): string|null
	{
		return $this->icon?->value;
	}

	/**
	 * @return array<MenuItem>
	 */
	public function getItems(): array
	{
		return $this->items;
	}

	public function isDefaultOpen(): bool
	{
		return $this->defaultOpen;
	}

	/**
	 * @return array<string>
	 */
	public function getAllActiveLinks(): array
	{
		$links = [];
		foreach ($this->items as $item) {
			$links = array_merge($links, $item->getActiveLinks());
		}

		return $links;
	}

}
