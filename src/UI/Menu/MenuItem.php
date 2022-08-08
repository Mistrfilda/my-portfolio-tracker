<?php

declare(strict_types = 1);

namespace App\UI\Menu;

use App\UI\Icon\SvgIcon;

class MenuItem
{

	/**
	 * @param array<string> $additionalActivePresenters
	 */
	public function __construct(
		private string $presenter,
		private string $action,
		private SvgIcon|null $icon,
		private string $label,
		private array $additionalActivePresenters = [],
		private bool $onlySysadmin = false,
		private string|null $badge = null,
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
		return $this->icon?->value;
	}

	public function getLabel(): string
	{
		return $this->label;
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

		if ($this->presenter !== '') {
			$condition[] = $this->presenter . ':*';
		}

		return $condition;
	}

	public function getBadge(): string|null
	{
		return $this->badge;
	}

}
