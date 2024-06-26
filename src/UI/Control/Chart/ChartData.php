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

	/** @var array<int, int|float> */
	private array $data;

	/** @var array<int, string> */
	private array $backgroundColors;

	/** @var array<int, string> */
	private array $borderColors;

	public function __construct(
		private string $label,
		private bool $useBackgroundColors = true,
	)
	{
		$this->labels = [];
		$this->data = [];
		$this->backgroundColors = [];
		$this->borderColors = [];
	}

	public function add(string $label, int|float $item): void
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
			'label' => $this->label,
			'data' => $this->data,
			'backgroundColors' => $this->backgroundColors,
			'borderColors' => $this->borderColors,
		];
	}

	/**
	 * @return array<string>
	 */
	public function getLabels(): array
	{
		return $this->labels;
	}

	/**
	 * @return array<int, int|float>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * @return array<string>
	 */
	public function getBackgroundColors(): array
	{
		return $this->backgroundColors;
	}

	/**
	 * @return array<string>
	 */
	public function getBorderColors(): array
	{
		return $this->borderColors;
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
