<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Datagrid\Filter;

use App\Test\UpdatedTestCase;
use App\UI\Control\Datagrid\Filter\FilterBoolean;
use App\UI\Control\Datagrid\Filter\FilterDateRange;
use App\UI\Control\Datagrid\Filter\FilterNullState;
use App\UI\Control\Datagrid\Filter\FilterText;

class FilterTest extends UpdatedTestCase
{

	public function testTextFilterStoresAndClearsValue(): void
	{
		$filter = new FilterText('name', 'Jméno', 'name');

		$filter->setValue('name', 'Apple');

		self::assertTrue($filter->isValueSet());
		self::assertSame('Apple', $filter->getValue('name'));
		self::assertSame('Jméno: Apple', $filter->getActiveValueLabel());

		$filter->setValue('name', '');

		self::assertFalse($filter->isValueSet());
	}

	public function testBooleanFilterKeepsZeroValue(): void
	{
		$filter = new FilterBoolean('enabled', 'Aktivní', 'enabled');

		$filter->setValue('enabled', 0);

		self::assertTrue($filter->isValueSet());
		self::assertSame(0, $filter->getValue('enabled'));
		self::assertSame('Aktivní: Ne', $filter->getActiveValueLabel());
	}

	public function testDateRangeUsesIndependentParameters(): void
	{
		$filter = new FilterDateRange('orderDate', 'Datum nákupu', 'orderDate');

		$filter->setValue('orderDate_from', '2026-01-01');
		$filter->setValue('orderDate_to', '2026-01-31');

		self::assertSame(
			[
				'orderDate_from',
				'orderDate_to',
			],
			$filter->getParameterKeys(),
		);
		self::assertSame('Datum nákupu: 2026-01-01–2026-01-31', $filter->getActiveValueLabel());
	}

	public function testNullStateUsesConfiguredLabels(): void
	{
		$filter = new FilterNullState(
			'status',
			'Stav',
			'closedPosition',
			'Otevřené',
			'Uzavřené',
		);

		$filter->setValue('status', FilterNullState::NOT_NULL);

		self::assertSame('Stav: Uzavřené', $filter->getActiveValueLabel());
	}

}
