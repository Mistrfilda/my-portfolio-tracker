<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid;

use App\UI\Control\Datagrid\Datasource\IDataSource;

class DatagridFactory
{

	public function create(IDataSource $dataSource): Datagrid
	{
		return new Datagrid($dataSource);
	}

}
