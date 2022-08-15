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
use App\Utils\Datetime\DatetimeConst;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class DashboardValueBuilderTest extends UpdatedTestCase
{

	public function testDashboardValueBuilder(): void
	{
		$now = new ImmutableDateTime();
		$currencyMock = Mockery::mock(CurrencyConversion::class)->makePartial();
		$currencyMock->expects('getCurrentPrice')->andReturn(25.0);
		$currencyMock->expects('getUpdatedAt')->andReturn($now);

		$currencyConversionMock = Mockery::mock(CurrencyConversionRepository::class)->makePartial();
		$currencyConversionMock->expects('getCurrentCurrencyPairConversion')->andReturn(
			$currencyMock,
		);

		$values = (new DashboardValueBuilder($currencyConversionMock))->buildValues();

		$expectedDashboardValue = new DashboardValue(
			'USD - CZK',
			'25',
			TailwindColorConstant::BLUE,
			SvgIcon::CZECH_CROWN,
			sprintf(
				'Aktualizováno %s',
				$now->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
			),
		);

		self::assertCount(3, $values);
		self::assertEquals($expectedDashboardValue, $values[1]);
	}

}
