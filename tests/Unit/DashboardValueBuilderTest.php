<?php

declare(strict_types = 1);

namespace App\Test\Unit;

use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Dashboard\DashboardValue;
use App\Dashboard\DashboardValueBuilder;
use App\Test\UpdatedTestCase;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Mockery;

class DashboardValueBuilderTest extends UpdatedTestCase
{

	public function testDashboardValueBuilder(): void
	{
		$currencyMock = Mockery::mock(CurrencyConversion::class)->makePartial();
		$currencyMock->expects('getCurrentPrice')->andReturn(25.0);

		$currencyConversionMock = Mockery::mock(CurrencyConversionRepository::class)->makePartial();
		$currencyConversionMock->expects('getCurrentCurrencyPairConversion')->andReturn(
			$currencyMock,
		);

		$values = (new DashboardValueBuilder($currencyConversionMock))->buildValues();

		$expectedDashboardValue = new DashboardValue(
			'USD - CZK',
			'25',
			TailwindColorConstant::EMERALD,
			SvgIcon::DOLLAR,
		);

		self::assertCount(3, $values);
		self::assertEquals($expectedDashboardValue, $values[1]);
	}

}
