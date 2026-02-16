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
			'scope',
			'Rozsah analýzy',
			static function (StockAiAnalysisRun $run): string {
				if ($run->getStockTicker() !== null) {
					return sprintf('Akcie: %s (%s)', $run->getStockTicker(), (string) $run->getStockName());
				}

				$parts = [];
				if ($run->includesPortfolio()) {
					$parts[] = 'Portfolio';
				}

				if ($run->includesWatchlist()) {
					$parts[] = 'Watchlist';
				}

				if ($run->includesMarketOverview()) {
					$parts[] = 'Trhy';
				}

				if ($parts === []) {
					return '---';
				}

				return implode(', ', $parts);
			},
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
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
