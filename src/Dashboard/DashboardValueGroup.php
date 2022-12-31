<?php

declare(strict_types = 1);

namespace App\Dashboard;

class DashboardValueGroup
{

	/**
	 * @param array<DashboardValue> $positions
	 */
	public function __construct(
		private readonly DashboardValueGroupEnum $name,
		private readonly string $heading,
		private readonly string|null $description = null,
		private readonly array $positions = [],
	)
	{
	}

	public function getName(): DashboardValueGroupEnum
	{
		return $this->name;
	}

	public function getHeading(): string
	{
		return $this->heading;
	}

	public function getDescription(): string|null
	{
		return $this->description;
	}

	/**
	 * @return array<DashboardValue>
	 */
	public function getPositions(): array
	{
		return $this->positions;
	}

}
