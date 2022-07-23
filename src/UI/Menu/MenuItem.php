<?php

declare(strict_types = 1);

namespace App\UI\Menu;

class MenuItem
{

	/**
	 * @param array<MenuItem> $childrens
	 * @param array<string> $additionalActivePresenters
	 */
	public function __construct(
		private string $presenter,
		private string $action,
		private string|null $icon,
		private string $label,
		private array $childrens = [],
		private array $additionalActivePresenters = [],
		private bool $onlySysadmin = false,
	)
	{
	}

	public function getPresenter(): string
	{
		return $this->presenter;
	}

	public function getAction(): string
	{
		return $this->action;
	}

	public function getLink(): string
	{
		return $this->presenter . ':' . $this->action;
	}

	public function getIcon(): string|null
	{
		return $this->icon;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * @return array<MenuItem>
	 */
	public function getChildrens(): array
	{
		return $this->childrens;
	}

	public function isNested(): bool
	{
		return count($this->childrens) > 0;
	}

	public function isOnlySysadmin(): bool
	{
		return $this->onlySysadmin;
	}

	/**
	 * @return array<string>
	 */
	public function getActiveLinks(): array
	{
		$condition = array_map(
			static fn (string $presenter): string => $presenter . ':*',
			$this->additionalActivePresenters,
		);

		return $this->getChildrenLinks($condition);
	}

	/**
	 * @param array<string> $condition
	 * @return array<string>
	 */
	private function getChildrenLinks(array &$condition): array
	{
		if ($this->presenter !== '') {
			$condition[] = $this->presenter . ':*';
		}

		if (count($this->childrens) > 0) {
			foreach ($this->childrens as $children) {
				$children->getChildrenLinks($condition);
			}
		}

		return $condition;
	}

}
