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
use Nette\Application\UI\Presenter;
use Nette\Http\SessionSection;

class DatagridTest extends UpdatedTestCase
{

	/** @var array<string, mixed> */
	private array $sessionData = [];

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
			$this->createStub(SessionSection::class),
		);
	}

	public function testStateIsRestoredWhenReturningToTheSameGrid(): void
	{
		$grid = $this->createRememberedGrid();
		$grid->filter([new FilterValue('name', 'Apple')]);
		$grid->handleSort('name');
		$grid->handleChangePagination(30, 10);

		$returnedGrid = $this->createRememberedGrid();
		self::assertSame(['name' => 'Apple'], $returnedGrid->parameterFilters);
		self::assertSame('Apple', $returnedGrid->getFilters()->get('name')?->getValue('name'));
		self::assertSame(['name' => 'DESC'], $returnedGrid->sortFilters);
		self::assertSame(30, $returnedGrid->offset);
		self::assertSame(10, $returnedGrid->limit);

		$otherGrid = $this->createRememberedGrid('watchlist');
		self::assertSame([], $otherGrid->parameterFilters);
		self::assertSame([], $otherGrid->sortFilters);
		self::assertSame(0, $otherGrid->offset);
	}

	public function testClearedFiltersDoNotReappearAfterReturning(): void
	{
		$grid = $this->createRememberedGrid();
		$grid->filter([new FilterValue('name', 'Apple')]);
		$grid->handleChangePagination(20, 10);
		$grid->handleResetFilters();

		$returnedGrid = $this->createRememberedGrid();
		self::assertSame([], $returnedGrid->parameterFilters);
		self::assertFalse($returnedGrid->getFilters()->get('name')?->isValueSet());
		self::assertSame(0, $returnedGrid->offset);
	}

	public function testRemovedFiltersAndSortColumnsAreIgnoredWhenRestoringState(): void
	{
		$this->sessionData['Assets::grid'] = [
			'parameterFilters' => ['name' => 'Apple', 'removedFilter' => 'stale'],
			'sortFilters' => ['name' => 'invalid', 'removedColumn' => 'ASC'],
			'offset' => -10,
			'limit' => 0,
		];

		$grid = $this->createRememberedGrid();
		self::assertSame(['name' => 'Apple'], $grid->parameterFilters);
		self::assertSame([], $grid->sortFilters);
		self::assertSame(0, $grid->offset);
		self::assertSame(1, $grid->limit);
	}

	private function createRememberedGrid(string $name = 'grid'): Datagrid
	{
		$section = $this->createStub(SessionSection::class);
		$section->method('get')->willReturnCallback(fn (string $key): mixed => $this->sessionData[$key] ?? null);
		$section->method('set')->willReturnCallback(function (string $key, mixed $value): void {
			$this->sessionData[$key] = $value;
		});
		$grid = new Datagrid(
			$this->createStub(IDataSource::class),
			$this->createStub(FilterForm::class),
			$section,
		);
		$column = $grid->addColumnText('name', 'Jméno');
		$column->setFilterText();
		$column->setSortable();

		$presenter = new class extends Presenter {

		};
		$presenter->setParent(null, 'Assets');
		$presenter->addComponent($grid, $name);

		return $grid;
	}

}
