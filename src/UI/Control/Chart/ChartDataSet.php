<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use JsonSerializable;

class ChartDataSet implements JsonSerializable
{

	/**
	 * @param array<int|string, ChartData> $chartData
	 * @param array<string>|null $hardcodedLabels
	 */
	public function __construct(
		private array $chartData,
		private string $tooltipSuffix = '',
		private array|null $hardcodedLabels = null,
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

		if ($this->hardcodedLabels !== null) {
			$labels = $this->hardcodedLabels;
		}

		return [
			'labels' => $labels,
			'tooltipSuffix' => $this->tooltipSuffix,
			'datasets' => $datasets,
		];
	}

}
