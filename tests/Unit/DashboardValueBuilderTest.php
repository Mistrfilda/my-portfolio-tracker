<?php

declare(strict_types = 1);

namespace App\Test\Unit;

use App\Admin\AppAdminRepository;
use App\Dashboard\DashboardValue;
use App\Dashboard\DashboardValueBuilder;
use App\Test\UpdatedTestCase;
use App\UI\Icon\SvgIcon;
use Mockery;

class DashboardValueBuilderTest extends UpdatedTestCase
{

	public function testDashboardValueBuilder(): void
	{
		$appAdminRepositoryMock = Mockery::mock(AppAdminRepository::class)->makePartial();
		$appAdminRepositoryMock->expects('getCount')->andReturn(3);

		$values = (new DashboardValueBuilder($appAdminRepositoryMock))->buildValues();

		$expectedDashboardValue = new DashboardValue(
			'Celkový počet uživatelů',
			'3',
			'blue',
			SvgIcon::GIFT,
		);

		self::assertCount(1, $values);
		self::assertEquals($expectedDashboardValue, $values[0]);
	}

}
