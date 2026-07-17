<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Datagrid;

use App\Test\UpdatedTestCase;
use App\UI\Control\Datagrid\Column\ColumnAlignmentEnum;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\Datasource\IDataSource;
use App\UI\Control\Datagrid\Filter\FilterForm;
use App\UI\Control\Datagrid\Filter\FilterValue;
use Mockery;

class DatagridTest extends UpdatedTestCase
{

	public function testColumnDisplaySettingsHaveBackwardCompatibleDefaults(): void
	{
		$grid = $this->createDatagrid();
		$column = $grid->addColumnText('name', 'Jméno');

		self::assertTrue($column->isDefaultVisible());
		self::assertTrue($column->isMobileVisible());
		self::assertTrue($column->isHideable());
		self::assertSame(ColumnAlignmentEnum::LEFT, $column->getAlignment());

		$column
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setHideable(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		self::assertFalse($column->isDefaultVisible());
		self::assertFalse($column->isMobileVisible());
		self::assertFalse($column->isHideable());
		self::assertSame(ColumnAlignmentEnum::RIGHT, $column->getAlignment());
	}

	public function testRemovingFilterClearsAllItsParametersAndResetsOffset(): void
	{
		$grid = $this->createDatagrid();
		$filter = $grid->addFilterDateRange('orderDate', 'Datum nákupu', 'orderDate');

		$grid->filter([
			new FilterValue('orderDate_from', '2026-01-01'),
			new FilterValue('orderDate_to', '2026-01-31'),
		]);
		$grid->offset = 60;

		$grid->handleRemoveFilter('orderDate');

		self::assertSame([], $grid->parameterFilters);
		self::assertFalse($filter->isValueSet());
		self::assertSame(0, $grid->offset);
	}

	public function testNewFilterSubmissionRemovesPreviousEmptyValues(): void
	{
		$grid = $this->createDatagrid();
		$nameFilter = $grid->addFilterText('name', 'Jméno', 'name');
		$statusFilter = $grid->addFilterNullState(
			'status',
			'Stav',
			'closedPosition',
			'Otevřené',
			'Uzavřené',
		);

		$grid->filter([
			new FilterValue('name', 'Apple'),
			new FilterValue('status', 'null'),
		]);
		$grid->filter([
			new FilterValue('status', 'not_null'),
		]);

		self::assertFalse($nameFilter->isValueSet());
		self::assertSame('not_null', $statusFilter->getValue('status'));
		self::assertSame(['status' => 'not_null'], $grid->parameterFilters);
	}

	private function createDatagrid(): Datagrid
	{
		return new Datagrid(
			Mockery::mock(IDataSource::class),
			Mockery::mock(FilterForm::class),
		);
	}

}
