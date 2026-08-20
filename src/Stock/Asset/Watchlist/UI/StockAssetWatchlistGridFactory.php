<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist\UI;

use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\CurrencyFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class StockAssetWatchlistGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly StockAssetWatchlistRepository $stockAssetWatchlistRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($this->stockAssetWatchlistRepository->createQueryBuilder()),
		);

		$name = $grid->addColumnText('name', 'Jméno');
		$name->setFilterText();
		$name->setSortable();

		$ticker = $grid->addColumnText('ticker', 'Ticker');
		$ticker->setFilterText();
		$ticker->setSortable();
		$grid->addColumnText(
			'recommendedEntryPrice',
			'Doporučená vstupní cena',
			static function (StockAssetWatchlist $stockAssetWatchlist): string {
				if (
					$stockAssetWatchlist->getRecommendedEntryPrice() === null
					|| $stockAssetWatchlist->getCurrency() === null
				) {
					return Datagrid::NULLABLE_PLACEHOLDER;
				}

				return CurrencyFilter::format(
					$stockAssetWatchlist->getRecommendedEntryPrice(),
					$stockAssetWatchlist->getCurrency(),
				);
			},
		);
		$grid->addColumnDatetime('updatedAt', 'Aktualizováno')->setSortable();

		$grid->addAction(
			'edit',
			'Editovat',
			'StockAssetWatchlist:edit',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::PENCIL,
		);
		$grid->addAction(
			'delete',
			'Smazat',
			'delete!',
			[new DatagridActionParameter('id', 'id')],
			SvgIcon::BIN,
			TailwindColorConstant::RED,
			confirmationString: 'Opravdu chcete položku z jednoduchého watchlistu smazat?',
		);

		return $grid;
	}

}
