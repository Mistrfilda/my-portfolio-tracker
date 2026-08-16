<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan\UI;

use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class StockAiInvestmentPlanGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly StockAiInvestmentPlanRepository $investmentPlanRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->investmentPlanRepository->createQueryBuilder()),
		);
		$grid->setLimit(10);
		$grid->addColumnDatetime('createdAt', 'Vytvořeno')->setSortable();
		$grid->addColumnText(
			'requestedAmount',
			'Částka',
			static fn (StockAiInvestmentPlan $plan): string => sprintf(
				'%.2f %s',
				$plan->getRequestedAmount()->getPrice(),
				$plan->getRequestedAmount()->getCurrency()->value,
			),
		);
		$grid->addColumnText(
			'portfolioPercent',
			'Podíl portfolia',
			static fn (StockAiInvestmentPlan $plan): string => sprintf(
				'%.2f %%',
				$plan->getRequestedAmountToPortfolioPercent(),
			),
		);
		$grid->addColumnText(
			'status',
			'Stav',
			static fn (StockAiInvestmentPlan $plan): string => $plan->getProcessedAt() === null
				? 'Čeká na Codex'
				: 'Zpracováno',
		);
		$grid->addColumnDatetime('processedAt', 'Zpracováno')->setSortable();
		$grid->addAction(
			'detail',
			'Detail',
			'StockAiInvestmentPlan:detail',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::EYE,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
