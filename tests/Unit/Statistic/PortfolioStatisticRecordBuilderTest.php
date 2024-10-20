<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Dashboard\DashboardValueGroup;
use App\Dashboard\DashboardValueGroupEnum;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordBuilder;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\UI\Icon\SvgIcon;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PortfolioStatisticRecordBuilderTest extends TestCase
{

	private PortfolioStatisticRecordRepository|MockInterface $portfolioStatisticRecordRepository;

	private PortfolioStatisticRecordBuilder $portfolioStatisticRecordBuilder;

	private int $statisticRecordId = 1;

	protected function setUp(): void
	{
		$this->portfolioStatisticRecordRepository = Mockery::mock(PortfolioStatisticRecordRepository::class);
		$this->portfolioStatisticRecordBuilder = new PortfolioStatisticRecordBuilder(
			$this->statisticRecordId,
			$this->portfolioStatisticRecordRepository,
		);
	}

	public function testBuildValues(): void
	{
		$dashboardValueGroupEnumTest = DashboardValueGroupEnum::TOTAL_VALUES;
		$svgIconEnumTest = SvgIcon::PENCIL;
		$portfolioStatisticRecord = Mockery::mock(PortfolioStatisticRecord::class);

		$portfolioStatistic = new PortfolioStatistic(
			$portfolioStatisticRecord,
			new ImmutableDateTime(),
			$dashboardValueGroupEnumTest,
			'label',
			'value',
			'color',
			$svgIconEnumTest,
			'description',
			null,
			PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
			[],
		);

		$portfolioStatistics = new ArrayCollection([$portfolioStatistic]);
		$portfolioStatisticRecord->shouldReceive('getPortfolioStatistics')->andReturn($portfolioStatistics);

		$this->portfolioStatisticRecordRepository->shouldReceive('getById')
			->with($this->statisticRecordId)
			->once()
			->andReturn($portfolioStatisticRecord);

		$dashboardValues = $this->portfolioStatisticRecordBuilder->buildValues();

		$this->assertIsArray($dashboardValues);
		$this->assertInstanceOf(DashboardValueGroup::class, $dashboardValues[0]);
		$this->assertEquals('label', $dashboardValues[0]->getPositions()[0]->getLabel());
		$this->assertEquals('value', $dashboardValues[0]->getPositions()[0]->getValue());
		$this->assertEquals('color', $dashboardValues[0]->getPositions()[0]->getColor());
		$this->assertEquals($svgIconEnumTest, $dashboardValues[0]->getPositions()[0]->getSvgIconEnum());
		$this->assertEquals('description', $dashboardValues[0]->getPositions()[0]->getDescription());
	}

}
