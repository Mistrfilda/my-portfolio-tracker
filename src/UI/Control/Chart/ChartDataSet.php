<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use JsonSerializable;

class ChartDataSet implements JsonSerializable
{

	/**
	 * @param array<int, ChartData> $chartData
	 */
	public function __construct(
		private array $chartData,
		private string $tooltipSuffix = '',
	)
	{
	}

	/**
	 * @return array<mixed>
	 */
	public function jsonSerialize(): array
	{
		$labels = [];
		$datasets = [];
		foreach ($this->chartData as $chartData) {
			$labels += $chartData->getLabels();
			$datasets[] = $chartData->jsonSerialize();
		}

		return [
			'labels' => $labels,
			'tooltipSuffix' => $this->tooltipSuffix,
			'datasets' => $datasets,
		];
	}

}
