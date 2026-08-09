<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Chart;

use App\UI\Control\Chart\ChartData;
use PHPUnit\Framework\TestCase;

class ChartDataTest extends TestCase
{

	public function testSerializesLineConfiguration(): void
	{
		$chartData = new ChartData(
			'Invested',
			useBackgroundColors: false,
			stepped: true,
			tension: 0.0,
			weeklyValueLabels: true,
		);
		$chartData->add('2026-08-01', 100);
		$chartData->add('2026-08-02', 150);

		self::assertSame([
			'label' => 'Invested',
			'data' => [100, 150],
			'backgroundColors' => [],
			'borderColors' => [],
			'stepped' => true,
			'weeklyValueLabels' => true,
			'tension' => 0.0,
		], $chartData->jsonSerialize());
	}

}
