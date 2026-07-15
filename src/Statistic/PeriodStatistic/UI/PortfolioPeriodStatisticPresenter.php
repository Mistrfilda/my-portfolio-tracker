<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\UI;

use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticFacade;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

/**
 * @property-read PortfolioPeriodStatisticTemplate $template
 */
class PortfolioPeriodStatisticPresenter extends BaseAdminPresenter
{

	private PortfolioPeriodStatistic|null $report = null;

	public function __construct(
		private PortfolioPeriodStatisticFacade $portfolioPeriodStatisticFacade,
		private PortfolioPeriodStatisticGridFactory $portfolioPeriodStatisticGridFactory,
		private PortfolioPeriodStatisticFormFactory $portfolioPeriodStatisticFormFactory,
		private ChartControlFactory $chartControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Přehled portfolia';
		$this->template->report = null;
	}

	public function actionDetail(string $id): void
	{
		$this->report = $this->portfolioPeriodStatisticFacade->get($id);
		$this->template->report = $this->report;
	}

	public function renderDetail(string $id): void
	{
		$this->template->heading = 'Přehled portfolia';
	}

	public function handleRetry(string $id): void
	{
		$this->portfolioPeriodStatisticFacade->retry($id);
		$this->flashMessage('Přehled byl znovu zařazen do fronty.', FlashMessageType::SUCCESS);
		$this->redirect('detail', ['id' => $id]);
	}

	public function handleRegenerate(string $id): void
	{
		$report = $this->portfolioPeriodStatisticFacade->regenerate($id);
		$this->flashMessage('Nová verze přehledu byla zařazena do fronty.', FlashMessageType::SUCCESS);
		$this->redirect('detail', ['id' => $report->getId()->toString()]);
	}

	protected function createComponentPortfolioPeriodStatisticForm(): AdminForm
	{
		return $this->portfolioPeriodStatisticFormFactory->create(function (
			PortfolioPeriodStatistic $report,
		): void {
			$this->flashMessage('Přehled byl zařazen do fronty.', FlashMessageType::SUCCESS);
			$this->redirect('detail', ['id' => $report->getId()->toString()]);
		});
	}

	protected function createComponentPortfolioPeriodStatisticGrid(): Datagrid
	{
		return $this->portfolioPeriodStatisticGridFactory->create();
	}

	protected function createComponentPortfolioValueChart(): ChartControl
	{
		return $this->chartControlFactory->create(
			ChartType::LINE,
			$this->createChartDataProvider(PortfolioPeriodStatisticChartTypeEnum::PORTFOLIO_VALUE),
		);
	}

	protected function createComponentDividendByCompanyChart(): ChartControl
	{
		return $this->chartControlFactory->create(
			ChartType::BAR,
			$this->createChartDataProvider(PortfolioPeriodStatisticChartTypeEnum::DIVIDENDS_BY_COMPANY),
		);
	}

	private function createChartDataProvider(
		PortfolioPeriodStatisticChartTypeEnum $type,
	): PortfolioPeriodStatisticChartDataProvider
	{
		if ($this->report?->getChartSection() === null) {
			$this->error('Data grafu nejsou dostupná.');
		}

		return new PortfolioPeriodStatisticChartDataProvider(
			$this->report->getId()->toString(),
			$this->report->getChartSection(),
			$type,
		);
	}

}
