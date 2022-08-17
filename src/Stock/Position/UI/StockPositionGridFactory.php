<?php

declare(strict_types = 1);

namespace App\Stock\Position\UI;

use App\Asset\Price\AssetPriceRenderer;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\PriceDiff;
use App\Currency\CurrencyConversionFacade;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class StockPositionGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly StockPositionRepository $stockPositionRepository,
		private readonly AssetPriceRenderer $assetPriceRenderer,
		private readonly AssetPriceService $assetPriceService,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function create(): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->stockPositionRepository->createQueryBuilderForDatagrid(),
			),
		);

		$grid->setLimit(30);

		$stockAsset = $grid->addColumnText(
			'stockAsset',
			'Akcie',
			static fn (StockPosition $stockPosition): string => $stockPosition->getAsset()->getName(),
			'stockAsset.name',
		);

		$stockAsset->setFilterText();
		$stockAsset->setSortable();

		$grid->addColumnDate('orderDate', 'Datum nákupu')
			->setSortable();

		$grid->addColumnBadge(
			'orderPiecesCount',
			'Počet kusů',
			TailwindColorConstant::BLUE,
		);

		$pricePerPiece = $grid->addColumnText(
			'pricePerPiece',
			'Cena za kus',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getPricePerPiece(),
			),
		);
		$pricePerPiece->setSortable();

		$grid->addColumnText(
			'currency',
			'Měna',
			static fn (StockPosition $stockPosition): string => $stockPosition->getAsset()->getCurrency()->format(),
			'stockAsset.currency',
		)->setSortable();

		$grid->addColumnText(
			'totalInvestedAmount',
			'Celková investovaná částka',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getTotalInvestedAmount(),
			),
		);

		$grid->addColumnText(
			'totalInvestedAmount',
			'Celková investovaná částka v měně brokera',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getTotalInvestedAmountInBrokerCurrency(),
			),
		);

		$grid->addColumnText(
			'currentTotalAmount',
			'Aktuální hodnota pozice',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getCurrentTotalAmount(),
			),
		);

		$summaryPriceCallback = fn (StockPosition $stockPosition): PriceDiff => $this->assetPriceService->getAssetPriceDiff(
			$this->currencyConversionFacade->getConvertedAssetPrice(
				$stockPosition->getCurrentTotalAmount(),
				$stockPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency(),
			),
			$stockPosition->getTotalInvestedAmountInBrokerCurrency(),
		);

		$grid->addColumnBadge(
			'summaryPrice',
			'Zisk/ztráta',
			TailwindColorConstant::GREEN,
			static function (StockPosition $stockPosition) use ($summaryPriceCallback): string {
				$priceDiff = $summaryPriceCallback($stockPosition);

				return CurrencyFilter::format($priceDiff->getPriceDifference(), $priceDiff->getCurrencyEnum());
			},
			static fn (StockPosition $stockPosition): string => $summaryPriceCallback($stockPosition)->getTrend()->getTailwindColor(),
			static fn (StockPosition $stockPosition): SvgIcon => $summaryPriceCallback($stockPosition)->getTrend()->getSvgIcon(),
		);

		$grid->addColumnBadge(
			'summaryPricePercentage',
			'Zisk/ztráta v %',
			TailwindColorConstant::GREEN,
			static function (StockPosition $stockPosition) use ($summaryPriceCallback): string {
				$priceDiff = $summaryPriceCallback($stockPosition);

				return PercentageFilter::format($priceDiff->getPercentageDifference());
			},
			static fn (StockPosition $stockPosition): string => $summaryPriceCallback($stockPosition)->getTrend()->getTailwindColor(),
			static fn (StockPosition $stockPosition): SvgIcon => $summaryPriceCallback($stockPosition)->getTrend()->getSvgIcon(),
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'StockPositionEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		return $grid;
	}

}
