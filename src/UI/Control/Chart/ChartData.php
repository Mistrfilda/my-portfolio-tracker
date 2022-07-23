<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use JsonSerializable;
use function random_int;
use function sprintf;

class ChartData implements JsonSerializable
{

	/** @var array<int, string> */
	private array $labels;

	/** @var array<int, int> */
	private array $data;

	/** @var array<int, string> */
	private array $backgroundColors;

	/** @var array<int, string> */
	private array $borderColors;

	public function __construct(
		private string $label,
		private bool $useBackgroundColors = true,
		private string $tooltipSuffix = '',
	)
	{
		$this->labels = [];
		$this->data = [];
		$this->backgroundColors = [];
		$this->borderColors = [];
	}

	public function add(string $label, int $item): void
	{
		$this->labels[] = $label;
		$this->data[] = $item;

		if ($this->useBackgroundColors) {
			$this->generateColorsForItem();
		}
	}

	/**
	 * @return array<mixed>
	 */
	public function jsonSerialize(): array
	{
		return [
			'labels' => $this->labels,
			'tooltipSuffix' => $this->tooltipSuffix,
			'datasets' => [
				'label' => $this->label,
				'data' => $this->data,
				'backgroundColors' => $this->backgroundColors,
				'borderColors' => $this->borderColors,
			],
		];
	}

	private function generateColorsForItem(): void
	{
		$r = random_int(1, 255);
		$g = random_int(1, 255);
		$b = random_int(1, 255);

		$this->backgroundColors[] = sprintf('rgba(%d, %d, %d, 0.2)', $r, $g, $b);
		$this->borderColors[] = sprintf('rgba(%d, %d, %d, 1)', $r, $g, $b);
	}

}
