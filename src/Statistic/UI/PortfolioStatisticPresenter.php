<?php

declare(strict_types = 1);

namespace App\Statistic\UI;

use App\Dashboard\UI\DashboardValueControl\DashboardValueControl;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControlFactory;
use App\Statistic\PortfolioStatisticRecordBuilder;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\Utils\Datetime\DatetimeConst;

class PortfolioStatisticPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly PortfolioStatisticRecordGridFactory $portfolioStatisticRecordGridFactory,
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private readonly DashboardValueControlFactory $dashboardValueControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Statistiky';
	}

	public function renderDetail(int $id): void
	{
		$record = $this->portfolioStatisticRecordRepository->getById($id);
		$this->template->heading = sprintf(
			'Statistiky ze dne %s',
			$record->getCreatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
		);
	}

	protected function createComponentPortfolioStatisticRecordGrid(): Datagrid
	{
		return $this->portfolioStatisticRecordGridFactory->create();
	}

	protected function createComponentDashboardValueControl(): DashboardValueControl
	{
		return $this->dashboardValueControlFactory->create(
			new PortfolioStatisticRecordBuilder(
				$this->processRequiredParameterInt(),
				$this->portfolioStatisticRecordRepository,
			),
		);
	}

}
