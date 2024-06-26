<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\AssetPriceFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class StockAssetGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private StockAssetRepository $stockAssetRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->stockAssetRepository->createQueryBuilder(),
			),
		);

		$grid->setLimit(30);

		$name = $grid->addColumnText('name', 'Jméno');
		$name->setFilterText();
		$name->setSortable();

		$grid->addColumnBadge('ticker', 'Ticker', TailwindColorConstant::BLUE)->setFilterText();

		$exchange = $grid->addColumnText('exchange', 'Burza');
		$exchange->setFilterText();
		$exchange->setSortable();

		$grid->addColumnText('currency', 'Měna')->setSortable();

		$grid->addColumnText('assetPriceDownloader', 'Zdroj dat');

		$grid->addColumnDatetime('priceDownloadedAt', 'Poslední aktualizace ceny')
			->setSortable();

		$grid->addColumnText(
			'currentPrice',
			'Aktualní cena',
			static fn (StockAsset $stockAsset): string => AssetPriceFilter::format($stockAsset->getAssetCurrentPrice()),
		);

		$grid->addColumnText(
			'paysDividend',
			'Vyplácí dividendy',
			static function (StockAsset $stockAsset): string {
				if ($stockAsset->doesPaysDividends()) {
					return 'Ano';
				}

				return 'Ne';
			},
		);

		$grid->addColumnText(
			'downloadPrice',
			'Aktualizace ceny',
			static function (StockAsset $stockAsset): string {
				if ($stockAsset->shouldBeUpdated()) {
					return 'Ano';
				}

				return 'Ne';
			},
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'StockAssetEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'dividends',
			'Dividendy',
			'StockAssetDividend:default',
			[
				new DatagridActionParameter('stockAssetId', 'id'),
			],
			SvgIcon::DOLLAR,
			TailwindColorConstant::EMERALD,
		);

		return $grid;
	}

}
