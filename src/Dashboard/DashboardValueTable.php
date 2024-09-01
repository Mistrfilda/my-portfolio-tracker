<?php

declare(strict_types = 1);

namespace App\Dashboard;

class DashboardValueTable
{

	/**
	 * @param array<mixed> $heading
	 * @param array<mixed> $data
	 */
	public function __construct(
		private readonly string $label,
		private readonly string $value,
		private readonly string $color,
		private array $heading,
		private array $data = [],
	)
	{
	}

	/**
	 * @param array<mixed> $data
	 */
	public function addData(array $data): void
	{
		$this->data[] = $data;
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

	/**
	 * @return array<mixed>
	 */
	public function getHeading(): array
	{
		return $this->heading;
	}

	/**
	 * @return array<mixed>
	 */
	public function getData(): array
	{
		return $this->data;
	}

}
