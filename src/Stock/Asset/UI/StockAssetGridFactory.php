<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Column\ColumnAlignmentEnum;
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
		$grid->enableColumnSelection();
		$grid->setCompact();
		$grid->setActionsInDropdown();

		$name = $grid->addColumnText('name', 'Jméno');
		$name->setFilterText();
		$name->setSortable();

		$grid->addColumnBadge('ticker', 'Ticker', TailwindColorConstant::BLUE)->setFilterText();

		$exchange = $grid->addColumnText('exchange', 'Burza');
		$exchange->setFilterSelect(StockAssetExchange::getOptionsForAdminSelect());
		$exchange->setSortable();

		$currency = $grid->addColumnText('currency', 'Měna');
		$currency->setFilterSelect(CurrencyEnum::getOptionsForAdminSelect());
		$currency->setSortable();
		$currency->setMobileVisible(false);

		$assetPriceDownloader = $grid->addColumnText('assetPriceDownloader', 'Zdroj dat')
			->setDefaultVisible(false)
			->setMobileVisible(false);
		$assetPriceDownloader->setFilterSelect(StockAssetPriceDownloaderEnum::getOptionsForAdminSelect());

		$priceDownloadedAt = $grid->addColumnDatetime('priceDownloadedAt', 'Poslední aktualizace ceny');
		$priceDownloadedAt->setFilterDateRange();
		$priceDownloadedAt->setSortable();

		$grid->addColumnText(
			'industry',
			'Odvětví',
			static fn (StockAsset $stockAsset): string|null => $stockAsset->getIndustry()?->getName(),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false);

		$grid->addColumnText(
			'currentPrice',
			'Aktualní cena',
			static fn (StockAsset $stockAsset): string => AssetPriceFilter::format($stockAsset->getAssetCurrentPrice()),
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'paysDividend',
			'Vyplácí dividendy',
			static function (StockAsset $stockAsset): string {
				if ($stockAsset->doesPaysDividends()) {
					return 'Ano';
				}

				return 'Ne';
			},
		)
			->setDefaultVisible(false)
			->setMobileVisible(false);

		$grid->addFilterNullState(
			'dividends',
			'Dividendy',
			'stockAssetDividendSource',
			'Bez dividend',
			'Vyplácí dividendy',
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
		)
			->setDefaultVisible(false)
			->setMobileVisible(false);

		$grid->addFilterBoolean(
			'shouldDownloadPrice',
			'Aktualizace ceny',
			'shouldDownloadPrice',
		);

		$grid->addColumnText(
			'valuation',
			'Stahování valuace',
			static function (StockAsset $stockAsset): string {
				if ($stockAsset->shouldDownloadValuation()) {
					return 'Ano';
				}

				return 'Ne';
			},
		)
			->setDefaultVisible(false)
			->setMobileVisible(false);

		$grid->addFilterBoolean(
			'shouldDownloadValuation',
			'Stahování valuace',
			'shouldDownloadValuation',
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

		$grid->addAction(
			'detail',
			'Detail',
			'StockAssetDetail:detail',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::EYE,
			TailwindColorConstant::INDIGO,
		);

		return $grid;
	}

}
