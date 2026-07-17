<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid;

use App\UI\Control\Datagrid\Datasource\IDataSource;
use App\UI\Control\Datagrid\Filter\FilterForm;
use App\UI\Control\Form\AdminFormFactory;

class DatagridFactory
{

	public function __construct(private AdminFormFactory $adminFormFactory)
	{
	}

	public function create(IDataSource $dataSource): Datagrid
	{
		return new Datagrid($dataSource, new FilterForm($this->adminFormFactory));
	}

}
