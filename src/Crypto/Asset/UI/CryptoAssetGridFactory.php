<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI;

use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\AssetPriceFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class CryptoAssetGridFactory
{

	public function __construct(
		private DatagridFactory $datagridFactory,
		private CryptoAssetRepository $cryptoAssetRepository,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->cryptoAssetRepository->createQueryBuilder(),
			),
		);

		$grid->setLimit(30);

		$name = $grid->addColumnText('name', 'Jméno');
		$name->setFilterText();
		$name->setSortable();

		$grid->addColumnBadge('ticker', 'Ticker', TailwindColorConstant::BLUE)->setFilterText();

		$grid->addColumnText('currency', 'Měna')->setSortable();

		$grid->addColumnDatetime('priceDownloadedAt', 'Poslední aktualizace ceny')
			->setSortable();

		$grid->addColumnText(
			'currentPrice',
			'Aktualní cena',
			static fn (CryptoAsset $cryptoAsset): string => AssetPriceFilter::format(
				$cryptoAsset->getAssetCurrentPrice(),
			),
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'CryptoAssetEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'detail',
			'Detail',
			'CryptoAssetDetail:detail',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::DOLLAR,
			TailwindColorConstant::INDIGO,
		);

		return $grid;
	}

}
