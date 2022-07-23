<?php

declare(strict_types = 1);

namespace App\Dashboard;

class DashboardValue
{

	public function __construct(
		private string $label,
		private string $value,
		private string $color,
		private string|null $svgIcon,
	)
	{
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getValue(): string
	{
		return $this->value;
	}

	public function getColor(): string
	{
		return $this->color;
	}

	public function getSvgIcon(): string|null
	{
		return $this->svgIcon;
	}

}
