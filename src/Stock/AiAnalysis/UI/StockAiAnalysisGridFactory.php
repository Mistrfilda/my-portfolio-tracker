<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class StockAiAnalysisGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private StockAiAnalysisRunRepository $stockAiAnalysisRunRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->stockAiAnalysisRunRepository->createQueryBuilder(),
			),
		);

		$grid->setLimit(30);

		$grid->addColumnDatetime('createdAt', 'Vytvořeno')->setSortable();

		$grid->addColumnText(
			'includesPortfolio',
			'Portfolio',
			static fn (StockAiAnalysisRun $run): string => $run->includesPortfolio() ? 'Ano' : 'Ne',
		);

		$grid->addColumnText(
			'includesWatchlist',
			'Watchlist',
			static fn (StockAiAnalysisRun $run): string => $run->includesWatchlist() ? 'Ano' : 'Ne',
		);

		$grid->addColumnText(
			'includesMarketOverview',
			'Trhy',
			static fn (StockAiAnalysisRun $run): string => $run->includesMarketOverview() ? 'Ano' : 'Ne',
		);

		$grid->addColumnText(
			'status',
			'Stav',
			static function (StockAiAnalysisRun $run): string {
				if ($run->getProcessedAt() !== null) {
					return 'Zpracováno';
				}

				if ($run->getRawResponse() !== null) {
					return 'Odpověď nahrána';
				}

				return 'Čeká na odpověď';
			},
		);

		$grid->addColumnDatetime('processedAt', 'Zpracováno')->setSortable();

		$grid->addAction(
			'detail',
			'Detail',
			'StockAiAnalysis:detail',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::EYE,
			TailwindColorConstant::INDIGO,
		);

		return $grid;
	}

}
