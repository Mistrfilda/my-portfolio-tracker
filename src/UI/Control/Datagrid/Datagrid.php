<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid;

use App\UI\Base\BaseControl;
use App\UI\Control\Datagrid\Action\DatagridAction;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Action\IDatagridAction;
use App\UI\Control\Datagrid\Column\ColumnBadge;
use App\UI\Control\Datagrid\Column\ColumnBadgeArray;
use App\UI\Control\Datagrid\Column\ColumnDatetime;
use App\UI\Control\Datagrid\Column\ColumnText;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Datasource\IDataSource;
use App\UI\Control\Datagrid\Filter\FilterBoolean;
use App\UI\Control\Datagrid\Filter\FilterDateRange;
use App\UI\Control\Datagrid\Filter\FilterForm;
use App\UI\Control\Datagrid\Filter\FilterNullState;
use App\UI\Control\Datagrid\Filter\FilterSelect;
use App\UI\Control\Datagrid\Filter\FilterText;
use App\UI\Control\Datagrid\Filter\FilterValue;
use App\UI\Control\Datagrid\Filter\IFilter;
use App\UI\Control\Datagrid\Pagination\Pagination;
use App\UI\Control\Datagrid\Pagination\PaginationService;
use App\UI\Control\Datagrid\Row\RowRenderer;
use App\UI\Control\Datagrid\Sort\Sort;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Control\Datagrid\Sort\SortException;
use App\UI\Control\Datagrid\Sort\SortService;
use App\UI\Control\Form\AdminForm;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Application\Attributes\Persistent;
use Nette\Utils\Json;

class Datagrid extends BaseControl
{

	public const NULLABLE_PLACEHOLDER = '----';

	#[Persistent]
	public int $offset;

	#[Persistent]
	public int $limit;

	/** @var array<string, string|int> */
	#[Persistent]
	public array $parameterFilters = [];

	/** @var array<string, string|null> */
	#[Persistent]
	public array $sortFilters = [];

	/** @var ArrayCollection<int, IColumn> */
	private ArrayCollection $columns;

	/** @var ArrayCollection<string, IFilter> */
	private ArrayCollection $filters;

	/** @var ArrayCollection<int, IDatagridAction> */
	private ArrayCollection $actions;

	private PaginationService $paginationService;

	/** @var ArrayCollection<string, Sort> */
	private ArrayCollection $sorts;

	private SortService $sortService;

	private bool $filterApplied = false;

	private bool $sortParametersApplied = false;

	private RowRenderer|null $rowRenderer = null;

	private bool $columnSelectionEnabled = false;

	private bool $compact = false;

	private bool $actionsInDropdown = false;

	public function __construct(
		private IDataSource $datasource,
		private FilterForm $filterForm,
	)
	{
		$this->setPagination();
		$this->paginationService = new PaginationService();
		$this->sortService = new SortService();
		$this->columns = new ArrayCollection();
		$this->filters = new ArrayCollection();
		$this->actions = new ArrayCollection();
		$this->sorts = new ArrayCollection();
	}

	public function addColumnText(
		string $column,
		string $label,
		callable|null $getterMethod = null,
		string|null $referencedColumn = null,
	): ColumnText
	{
		$column = new ColumnText(
			$this,
			$label,
			$column,
			$getterMethod,
			$referencedColumn,
		);

		$this->columns->add($column);

		return $column;
	}

	public function addColumnBadge(
		string $column,
		string $label,
		string $color,
		callable|null $getterMethod = null,
		callable|null $colorCallback = null,
		callable|null $svgIconCallback = null,
	): ColumnText
	{
		$column = new ColumnBadge(
			$this,
			$label,
			$column,
			$color,
			$getterMethod,
			$colorCallback,
			$svgIconCallback,
		);
		$this->columns->add($column);

		return $column;
	}

	public function addColumnBadgeArray(
		string $column,
		string $label,
		string $color,
		callable|null $getterMethod = null,
		callable|null $colorCallback = null,
	): ColumnText
	{
		$column = new ColumnBadgeArray(
			$this,
			$label,
			$column,
			$color,
			$getterMethod,
			$colorCallback,
		);
		$this->columns->add($column);

		return $column;
	}

	public function addColumnDatetime(
		string $column,
		string $label,
		callable|null $getterMethod = null,
		string|null $referencedColumn = null,
	): ColumnDatetime
	{
		$column = new ColumnDatetime($this, $label, $column, $getterMethod, $referencedColumn);
		$this->columns->add($column);

		return $column;
	}

	public function addColumnDate(
		string $column,
		string $label,
		callable|null $getterMethod = null,
	): ColumnDatetime
	{
		$column = new ColumnDatetime($this, $label, $column, $getterMethod);

		$column->setFormat(DatetimeFactory::DEFAULT_DATE_FORMAT);
		$this->columns->add($column);

		return $column;
	}

	/**
	 * @param array<DatagridActionParameter> $parameters
	 */
	public function addAction(
		string $id,
		string $label,
		string $destination,
		array $parameters,
		SvgIcon|null $icon = null,
		string $color = TailwindColorConstant::BLUE,
		bool $isAjax = false,
		string|null $confirmationString = null,
	): DatagridAction
	{
		$action = new DatagridAction(
			$this,
			$id,
			$label,
			$destination,
			$parameters,
			$icon,
			$color,
			isAjax: $isAjax,
			confirmationString: $confirmationString,
		);

		$this->actions->add($action);

		return $action;
	}

	public function setFilterText(ColumnText $column): FilterText
	{
		return $this->addFilterText(
			$column->getColumn(),
			$column->getLabel(),
			$column->getColumn(),
			$column->getReferencedColumn(),
		);
	}

	/**
	 * @param array<string|int, string> $options
	 */
	public function setFilterSelect(ColumnText $column, array $options): FilterSelect
	{
		return $this->addFilterSelect(
			$column->getColumn(),
			$column->getLabel(),
			$column->getColumn(),
			$options,
			$column->getReferencedColumn(),
		);
	}

	public function setFilterBoolean(ColumnText $column): FilterBoolean
	{
		return $this->addFilterBoolean(
			$column->getColumn(),
			$column->getLabel(),
			$column->getColumn(),
			$column->getReferencedColumn(),
		);
	}

	public function setFilterDateRange(ColumnDatetime $column): FilterDateRange
	{
		return $this->addFilterDateRange(
			$column->getColumn(),
			$column->getLabel(),
			$column->getColumn(),
			$column->getReferencedColumn(),
		);
	}

	public function addFilterText(
		string $key,
		string $label,
		string $column,
		string|null $referencedColumn = null,
	): FilterText
	{
		$filter = new FilterText($key, $label, $column, $referencedColumn);
		$this->filters->set($key, $filter);

		return $filter;
	}

	/**
	 * @param array<string|int, string> $options
	 */
	public function addFilterSelect(
		string $key,
		string $label,
		string $column,
		array $options,
		string|null $referencedColumn = null,
	): FilterSelect
	{
		$filter = new FilterSelect($key, $label, $column, $options, $referencedColumn);
		$this->filters->set($key, $filter);

		return $filter;
	}

	public function addFilterBoolean(
		string $key,
		string $label,
		string $column,
		string|null $referencedColumn = null,
	): FilterBoolean
	{
		$filter = new FilterBoolean($key, $label, $column, $referencedColumn);
		$this->filters->set($key, $filter);

		return $filter;
	}

	public function addFilterDateRange(
		string $key,
		string $label,
		string $column,
		string|null $referencedColumn = null,
	): FilterDateRange
	{
		$filter = new FilterDateRange($key, $label, $column, $referencedColumn);
		$this->filters->set($key, $filter);

		return $filter;
	}

	public function addFilterNullState(
		string $key,
		string $label,
		string $column,
		string $nullLabel,
		string $notNullLabel,
		string|null $referencedColumn = null,
	): FilterNullState
	{
		$filter = new FilterNullState(
			$key,
			$label,
			$column,
			$nullLabel,
			$notNullLabel,
			$referencedColumn,
		);
		$this->filters->set($key, $filter);

		return $filter;
	}

	public function setSortable(IColumn $column, SortDirectionEnum|null $defaultDirection = null): Sort
	{
		$sort = new Sort($column, $defaultDirection, $defaultDirection !== null);
		$this->sorts->set($column->getColumn(), $sort);

		return $sort;
	}

	public function handleChangePagination(int $offset, int $limit): void
	{
		$this->offset = $offset;
		$this->limit = $limit;
		$this->redrawGridData();
	}

	public function handleArrowLeft(): void
	{
		if ($this->offset !== 0) {
			$this->offset -= $this->limit;
		}

		$this->redrawGridData();
	}

	public function handleArrowRight(): void
	{
		if ($this->offset + $this->limit < $this->datasource->getCount($this->filters)) {
			$this->offset += $this->limit;
		}

		$this->redrawGridData();
	}

	public function handleResetFilters(): void
	{
		$this->parameterFilters = [];
		foreach ($this->filters as $filter) {
			$filter->clear();
		}

		$this->resetPagination();
		$this->filterApplied = true;
		$this->redrawControl('filters');
		$this->redrawGridData();
	}

	public function handleRemoveFilter(string $key): void
	{
		$filter = $this->filters->get($key);
		if ($filter === null) {
			return;
		}

		foreach ($filter->getParameterKeys() as $parameter) {
			unset($this->parameterFilters[$parameter]);
		}

		$filter->clear();
		$this->resetPagination();
		$this->filterApplied = true;
		$this->redrawControl('filters');
		$this->redrawGridData();
	}

	/**
	 * @param array<string, string|null> $defaultSortFilters
	 */
	public function handleSort(string $column, array|null $defaultSortFilters = null): void
	{
		if ($defaultSortFilters !== null) {
			$this->sortFilters = $defaultSortFilters;
		}

		$this->sortService->getFiltersFromParameters(
			$this->sortFilters,
			$this->sorts,
			true,
		);

		$sort = $this->sorts->get($column);
		if ($sort === null) {
			throw new SortException(sprintf('Unknown column %s', $column));
		}

		$this->sortService->setCurrentSortDirectionForColumn($sort);
		$this->sortFilters[$column] = $sort->getCurrentDirection()?->value;
		$this->sortParametersApplied = true;

		$this->redrawGridData();
	}

	public function getDatasource(): IDataSource
	{
		return $this->datasource;
	}

	public function render(): void
	{
		$template = $this->createTemplate(DatagridTemplate::class);

		if ($this->filterApplied === false && count($this->parameterFilters) > 0) {
			$values = [];
			foreach ($this->parameterFilters as $key => $value) {
				$values[] = new FilterValue($key, $value);
			}

			$this->filter($values);
		}

		$defaultSortFilters = null;
		if ($this->sortParametersApplied === false) {
			$this->sortService->getFiltersFromParameters(
				$this->sortFilters,
				$this->sorts,
			);

			$defaultSortFilters = [];
			foreach ($this->sorts as $sort) {
				if ($sort->getCurrentDirection() !== null) {
					$defaultSortFilters[$sort->getColumn()->getColumn()] = $sort->getCurrentDirection()->value;
				}
			}
		}

		$template->defaultSortFilters = $defaultSortFilters;

		$dataCount = $this->datasource->getCount($this->filters);

		$data = $this->datasource->getData(
			$this->offset,
			$this->limit,
			$this->filters,
			$this->sorts,
		);

		$template->filters = $this->filters;
		$template->columns = $this->columns;
		$template->actions = $this->actions;
		$template->rowRenderer = $this->rowRenderer;
		$template->columnSelectionEnabled = $this->columnSelectionEnabled;
		$template->compact = $this->compact;
		$template->actionsInDropdown = $this->actionsInDropdown;
		$defaultVisibleColumns = [];
		foreach ($this->columns as $column) {
			if ($column->isDefaultVisible()) {
				$defaultVisibleColumns[] = $column->getColumn();
			}
		}

		$template->defaultVisibleColumnsJson = Json::encode($defaultVisibleColumns);

		$template->activeFilterCount = count(array_filter(
			$this->filters->toArray(),
			static fn (IFilter $filter): bool => $filter->isValueSet(),
		));

		$template->pagination = new Pagination(
			$this->limit,
			$this->offset,
			$this->paginationService->getPagination(
				$this->offset,
				$this->limit,
				$dataCount,
			),
		);

		$template->itemsCount = $dataCount;
		$template->items = $data;
		$template->datasource = $this->datasource;

		$template->setFile(__DIR__ . '/datagrid.latte');
		$template->render();
	}

	public function setLimit(int $limit): void
	{
		$this->limit = $limit;
	}

	public function setPerPage(int $perPage): void
	{
		$this->setLimit($perPage);
	}

	public function setMaxResultSet(int $limit): void
	{
		$this->setLimit($limit);
	}

	/**
	 * @return ArrayCollection<string, IFilter>
	 */
	public function getFilters(): ArrayCollection
	{
		return $this->filters;
	}

	/**
	 * @param array<int, FilterValue> $values
	 */
	public function filter(array $values): void
	{
		$this->parameterFilters = [];
		foreach ($this->filters as $filter) {
			$filter->clear();
		}

		foreach ($values as $value) {
			foreach ($this->filters as $filter) {
				if ($filter->hasParameter($value->getKey())) {
					$filter->setValue($value->getKey(), $value->getValue());
					$this->parameterFilters[$value->getKey()] = $value->getValue();

					break;
				}
			}
		}

		$this->filterApplied = true;
		$this->redrawControl('filters');
		$this->redrawGridData();
	}

	protected function createComponentFilterForm(): AdminForm
	{
		return $this->filterForm->createForm($this);
	}

	public function resetPagination(): void
	{
		$this->offset = 0;
	}

	public function setRowRender(RowRenderer $rowRenderer): void
	{
		$this->rowRenderer = $rowRenderer;
	}

	public function enableColumnSelection(): void
	{
		$this->columnSelectionEnabled = true;
	}

	public function setCompact(bool $compact = true): void
	{
		$this->compact = $compact;
	}

	public function setActionsInDropdown(bool $actionsInDropdown = true): void
	{
		$this->actionsInDropdown = $actionsInDropdown;
	}

	private function setPagination(): void
	{
		$this->offset = 0;
		$this->limit = 10;
	}

	public function redrawGridData(): void
	{
		$this->redrawControl('items');
		$this->redrawControl('pagination');
	}

}
