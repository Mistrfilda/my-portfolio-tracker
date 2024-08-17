<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

class PortfolioStatisticChart
{

	public function __construct(
		private string $title,
		private string|null $description,
		private string $control,
	)
	{
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getDescription(): string|null
	{
		return $this->description;
	}

	public function getControl(): string
	{
		return $this->control;
	}

}
