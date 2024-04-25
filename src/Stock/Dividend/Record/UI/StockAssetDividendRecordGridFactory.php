<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record\UI;

use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Control\Datagrid\Row\BaseRowRenderer;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\SummaryPriceFilter;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDividendRecordGridFactory
{

	public function __construct(
		private DatagridFactory $gridFactory,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->gridFactory->create(
			new DoctrineDataSource($this->stockAssetDividendRecordRepository->createQueryBuilder()),
		);

		$stockAsset = $grid->addColumnText(
			'stockAsset',
			'Akcie',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => $stockAssetDividendRecord
				->getStockAssetDividend()
				->getStockAsset()
				->getName(),
			'stockAsset.name',
		);

		$stockAsset->setFilterText();
		$stockAsset->setSortable();

		$grid->addColumnDatetime(
			'exDate',
			'Ex date',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): ImmutableDateTime => $stockAssetDividendRecord
				->getStockAssetDividend()
				->getExDate(),
			'stockAssetDividend.exDate',
		)->setSortable(SortDirectionEnum::DESC);

		$grid->addColumnDatetime(
			'paymentDate',
			'Datum vyplacení',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): ImmutableDateTime|null => $stockAssetDividendRecord
				->getStockAssetDividend()
				->getPaymentDate(),
		);

		$grid->addColumnText(
			'dividendPerShare',
			'Dividenda na akcii',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => CurrencyFilter::format(
				$stockAssetDividendRecord
					->getStockAssetDividend()
					->getAmount(),
				$stockAssetDividendRecord
					->getStockAssetDividend()
					->getCurrency(),
			),
		);

		$grid->addColumnText(
			'totalPiecesHeldAtExDate',
			'Počet držených akcií (ex date)',
		);

		$grid->addColumnText(
			'totalAmount',
			'Celková hodnota před zdaněním',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => CurrencyFilter::format(
				$stockAssetDividendRecord->getTotalAmount(),
				$stockAssetDividendRecord->getStockAssetDividend()->getCurrency(),
			),
		);

		$grid->addColumnText(
			'totalAmount',
			'Celková hodnota po zdanění',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => SummaryPriceFilter::format($stockAssetDividendRecord->getSummaryPrice()),
		);

		$now = $this->datetimeFactory->createNow();
		$grid->setRowRender(
			new BaseRowRenderer(
				static function (StockAssetDividendRecord $stockAssetDividendRecord) use ($now): string {
					if ($stockAssetDividendRecord->getStockAssetDividend()->isPaid($now)) {
						return 'bg-blue-300';
					}

					return 'bg-emerald-300';
				},
			),
		);

		return $grid;
	}

}
