<?php

declare(strict_types = 1);

namespace App\Admin\UI;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class AppAdminGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private AppAdminRepository $appAdminRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->appAdminRepository->createQueryBuilder(),
			),
		);

		$grid->addColumnText('name', 'Jméno')->setFilterText();
		$grid->addColumnText('username', 'Uživatelské jméno')->setFilterText();
		$grid->addColumnBadge('email', 'Email', TailwindColorConstant::BLUE)->setFilterText();
		$grid->addColumnDatetime('createdAt', 'Vytvořen');
		$grid->addColumnDatetime('createdAt', 'Poslední aktualizace');

		$grid->addColumnText(
			'sysAdmin',
			'Systém administrátor',
			static function (AppAdmin $appAdmin): string {
				if ($appAdmin->isSysAdmin()) {
					return 'Ano';
				}

				return 'Ne';
			},
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'AppAdminEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
