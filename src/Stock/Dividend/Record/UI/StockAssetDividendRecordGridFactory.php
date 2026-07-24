<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record\UI;

use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Control\Datagrid\Column\ColumnAlignmentEnum;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Control\Datagrid\Sort\SortDirectionEnum;
use App\UI\Filter\BooleanFilter;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\SummaryPriceFilter;
use App\UI\Tailwind\TailwindColorConstant;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendRecordGridFactory
{

	public function __construct(
		private DatagridFactory $gridFactory,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(UuidInterface|null $id = null): Datagrid
	{
		$qb = $this->stockAssetDividendRecordRepository->createQueryBuilder();

		if ($id !== null) {
			$qb->andWhere(
				$qb->expr()->eq('stockAsset.id', ':id'),
			);
			$qb->setParameter('id', $id);
		}

		$grid = $this->gridFactory->create(
			new DoctrineDataSource($qb),
		);
		$grid->enableColumnSelection();
		$grid->setCompact();

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

		$exDate = $grid->addColumnDatetime(
			'exDate',
			'Ex date',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): ImmutableDateTime => $stockAssetDividendRecord
				->getStockAssetDividend()
				->getExDate(),
			'stockAssetDividend.exDate',
		);
		$exDate->setFormat(DatetimeFactory::DEFAULT_DATE_FORMAT);
		$exDate->setSortable(SortDirectionEnum::DESC);

		$grid->addColumnDate(
			'paymentDate',
			'Datum vyplacení',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): ImmutableDateTime|null => $stockAssetDividendRecord
				->getStockAssetDividend()
				->getPaymentDate(),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false);

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
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'totalPiecesHeldAtExDate',
			'Počet držených akcií (ex date)',
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'totalAmountGross',
			'Celková hodnota před zdaněním',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => CurrencyFilter::format(
				$stockAssetDividendRecord->getTotalAmount(),
				$stockAssetDividendRecord->getStockAssetDividend()->getCurrency(),
			),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'totalAmountNet',
			'Celková hodnota po zdanění',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => SummaryPriceFilter::format(
				$stockAssetDividendRecord->getSummaryPrice(),
			),
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'reinvested',
			'Reinvestováno',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => BooleanFilter::format(
				$stockAssetDividendRecord->isReinvested(),
			),
		)->setAlignment(ColumnAlignmentEnum::CENTER);

		$now = $this->datetimeFactory->createNow();
		$status = $grid->addColumnBadge(
			'status',
			'Stav',
			TailwindColorConstant::EMERALD,
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => $stockAssetDividendRecord
				->getStockAssetDividend()
				->isPaid($now)
					? 'Očekávaná'
					: 'Vyplacená',
			static fn (StockAssetDividendRecord $stockAssetDividendRecord): string => $stockAssetDividendRecord
				->getStockAssetDividend()
				->isPaid($now)
					? TailwindColorConstant::BLUE
					: TailwindColorConstant::EMERALD,
		);
		$status->setAlignment(ColumnAlignmentEnum::CENTER);

		return $grid;
	}

}
