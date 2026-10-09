<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid;

use App\UI\Control\Datagrid\Datasource\IDataSource;
use App\UI\Control\Datagrid\Filter\FilterForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Http\Session;
use Nette\Security\User;

class DatagridFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private Session $session,
		private User $user,
	)
	{
	}

	public function create(IDataSource $dataSource): Datagrid
	{
		return new Datagrid(
			$dataSource,
			new FilterForm($this->adminFormFactory),
			$this->session->getSection('datagrid.' . $this->user->getId()),
		);
	}

}
