<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Statistic\PortolioStatisticType;
use App\UI\Icon\SvgIcon;

class DashboardValue
{

	public function __construct(
		private readonly string $label,
		private readonly string $value,
		private readonly string $color,
		private readonly SvgIcon|null $svgIcon = null,
		private readonly string|null $description = null,
		private readonly PortolioStatisticType|null $type = null,
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

	public function getSvgIconEnum(): SvgIcon|null
	{
		return $this->svgIcon;
	}

	public function getSvgIcon(): string|null
	{
		return $this->svgIcon?->value;
	}

	public function getDescription(): string|null
	{
		return $this->description;
	}

	public function getType(): PortolioStatisticType|null
	{
		return $this->type;
	}

}
