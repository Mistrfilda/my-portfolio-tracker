<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Filter\CurrencyFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendGridFactory
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private DatagridFactory $datagridFactory,
	)
	{
	}

	public function create(UuidInterface $stockAssetId): Datagrid
	{
		$qb = $this->stockAssetDividendRepository->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
		);
		$qb->setParameter('stockAsset', $stockAssetId);

		$grid = $this->datagridFactory->create(
			new DoctrineDataSource($qb),
		);

		$grid->addColumnDatetime('exDate', 'Ex date')->setSortable(SortDirectionEnum::DESC);
		$grid->addColumnDatetime('paymentDate', 'Datum výplaty')->setSortable();
		$grid->addColumnDatetime('declarationDate', 'Datum deklarace');
		$grid->addColumnText(
			'amount',
			'Částka',
			static fn (StockAssetDividend $stockAssetDividend): string => CurrencyFilter::format(
				$stockAssetDividend->getAmount(),
				$stockAssetDividend->getCurrency(),
			),
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'StockAssetDividend:edit',
			[
				new DatagridActionParameter('id', 'id'),
				new DatagridActionParameter('stockAssetId', 'id', $stockAssetId->toString()),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
