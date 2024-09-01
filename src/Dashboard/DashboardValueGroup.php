<?php

declare(strict_types = 1);

namespace App\Dashboard;

class DashboardValueGroup
{

	/**
	 * @param array<DashboardValue> $positions
	 * @param array<DashboardValueTable> $tables
	 */
	public function __construct(
		private readonly DashboardValueGroupEnum $name,
		private readonly string $heading,
		private readonly string|null $description = null,
		private readonly array $positions = [],
		private readonly bool $isOpen = false,
		private readonly array $tables = [],
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

	public function isOpen(): bool
	{
		return $this->isOpen;
	}

	/**
	 * @return array<DashboardValueTable>
	 */
	public function getTables(): array
	{
		return $this->tables;
	}

}
