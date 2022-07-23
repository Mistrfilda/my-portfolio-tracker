<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid;

use App\Doctrine\IEntity;
use App\UI\Control\Datagrid\Action\IDatagridAction;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Datasource\IDataSource;
use App\UI\Control\Datagrid\Filter\IFilter;
use App\UI\Control\Datagrid\Pagination\Pagination;
use Doctrine\Common\Collections\ArrayCollection;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;

class DatagridTemplate extends Template
{

	public Presenter $presenter;

	public Datagrid $control;

	/** @var ArrayCollection<int, IColumn> */
	public ArrayCollection $columns;

	/** @var ArrayCollection<int, IFilter> */
	public ArrayCollection $filters;

	/** @var array<int|string, IEntity> */
	public array $items;

	/** @var ArrayCollection<int, IDatagridAction> */
	public ArrayCollection $actions;

	public IDataSource $datasource;

	public Pagination $pagination;

	public int $itemsCount;

}
