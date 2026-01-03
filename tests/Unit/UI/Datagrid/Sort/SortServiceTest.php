<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Datagrid\Sort;

use App\Test\UpdatedTestCase;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Sort\Sort;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Control\Datagrid\Sort\SortException;
use App\UI\Control\Datagrid\Sort\SortService;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery;

class SortServiceTest extends UpdatedTestCase
{

	private SortService $sortService;

	protected function setUp(): void
	{
		$this->sortService = new SortService();
	}

	public function testGetFiltersFromParametersSetsDirection(): void
	{
		$column = Mockery::mock(IColumn::class);
		$column->shouldReceive('getColumn')->andReturn('name');

		$sort = new Sort($column, null);
		$sorts = new ArrayCollection(['name' => $sort]);

		$this->sortService->getFiltersFromParameters(
			['name' => 'ASC'],
			$sorts,
		);

		$this->assertSame(SortDirectionEnum::ASC, $sort->getCurrentDirection());
	}

	public function testGetFiltersFromParametersSetsDescDirection(): void
	{
		$column = Mockery::mock(IColumn::class);
		$column->shouldReceive('getColumn')->andReturn('name');

		$sort = new Sort($column, null);
		$sorts = new ArrayCollection(['name' => $sort]);

		$this->sortService->getFiltersFromParameters(
			['name' => 'DESC'],
			$sorts,
		);

		$this->assertSame(SortDirectionEnum::DESC, $sort->getCurrentDirection());
	}

	public function testGetFiltersFromParametersResetsDirectionWhenNull(): void
	{
		$column = Mockery::mock(IColumn::class);
		$column->shouldReceive('getColumn')->andReturn('name');

		$sort = new Sort($column, SortDirectionEnum::ASC);
		$sorts = new ArrayCollection(['name' => $sort]);

		$this->sortService->getFiltersFromParameters(
			['name' => null],
			$sorts,
		);

		$this->assertNull($sort->getCurrentDirection());
	}

	public function testGetFiltersFromParametersThrowsExceptionForUnknownParameter(): void
	{
		$sorts = new ArrayCollection();

		self::assertException(
			fn () => $this->sortService->getFiltersFromParameters(
				['unknown' => 'ASC'],
				$sorts,
			),
			SortException::class,
			'Unknown parameter unknown',
		);
	}

	public function testGetFiltersFromParametersWithFromHandleResetsDefaultParameters(): void
	{
		$column1 = Mockery::mock(IColumn::class);
		$column1->shouldReceive('getColumn')->andReturn('name');

		$column2 = Mockery::mock(IColumn::class);
		$column2->shouldReceive('getColumn')->andReturn('date');

		$sort1 = new Sort($column1, SortDirectionEnum::DESC, true);
		$sort2 = new Sort($column2, SortDirectionEnum::ASC, false);

		$sorts = new ArrayCollection([
			'name' => $sort1,
			'date' => $sort2,
		]);

		$this->sortService->getFiltersFromParameters(
			['date' => 'DESC'],
			$sorts,
			true,
		);

		$this->assertNull($sort1->getCurrentDirection());
		$this->assertSame(SortDirectionEnum::DESC, $sort2->getCurrentDirection());
	}

	public function testGetFiltersFromParametersWithFromHandleKeepsNonDefaultParameters(): void
	{
		$column = Mockery::mock(IColumn::class);
		$column->shouldReceive('getColumn')->andReturn('name');

		$sort = new Sort($column, SortDirectionEnum::ASC, false);
		$sorts = new ArrayCollection(['name' => $sort]);

		$this->sortService->getFiltersFromParameters(
			[],
			$sorts,
			true,
		);

		$this->assertSame(SortDirectionEnum::ASC, $sort->getCurrentDirection());
	}

	public function testSetCurrentSortDirectionForColumnFromNull(): void
	{
		$column = Mockery::mock(IColumn::class);
		$sort = new Sort($column, null);

		$result = $this->sortService->setCurrentSortDirectionForColumn($sort);

		$this->assertSame(SortDirectionEnum::DESC, $result->getCurrentDirection());
		$this->assertSame($sort, $result);
	}

	public function testSetCurrentSortDirectionForColumnFromDesc(): void
	{
		$column = Mockery::mock(IColumn::class);
		$sort = new Sort($column, SortDirectionEnum::DESC);

		$result = $this->sortService->setCurrentSortDirectionForColumn($sort);

		$this->assertSame(SortDirectionEnum::ASC, $result->getCurrentDirection());
	}

	public function testSetCurrentSortDirectionForColumnFromAsc(): void
	{
		$column = Mockery::mock(IColumn::class);
		$sort = new Sort($column, SortDirectionEnum::ASC);

		$result = $this->sortService->setCurrentSortDirectionForColumn($sort);

		$this->assertNull($result->getCurrentDirection());
	}

	public function testSetCurrentSortDirectionForColumnCycles(): void
	{
		$column = Mockery::mock(IColumn::class);
		$sort = new Sort($column, null);

		// null -> DESC
		$this->sortService->setCurrentSortDirectionForColumn($sort);
		$this->assertSame(SortDirectionEnum::DESC, $sort->getCurrentDirection());

		// DESC -> ASC
		$this->sortService->setCurrentSortDirectionForColumn($sort);
		$this->assertSame(SortDirectionEnum::ASC, $sort->getCurrentDirection());

		// ASC -> null
		$this->sortService->setCurrentSortDirectionForColumn($sort);
		$this->assertNull($sort->getCurrentDirection());
	}

}
