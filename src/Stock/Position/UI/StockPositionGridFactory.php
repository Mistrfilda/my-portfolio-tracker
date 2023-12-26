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
use App\UI\Control\Datagrid\Row\BaseRowRenderer;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

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

		$grid->addColumnDate(
			'closedDate',
			'Uzavřena dne',
			static fn (StockPosition $stockPosition): ImmutableDateTime|null => $stockPosition->getStockClosedPosition()?->getDate(),
		);

		$grid->addColumnBadge(
			'isOpen',
			'Stav pozice',
			TailwindColorConstant::GREEN,
			static function (StockPosition $stockPosition): string {
				if ($stockPosition->isPositionClosed()) {
					return 'Uzavřená';
				}

				return 'Otevřená';
			},
			static function (StockPosition $stockPosition): string {
				if ($stockPosition->isPositionClosed()) {
					return TailwindColorConstant::YELLOW;
				}

				return TailwindColorConstant::BLUE;
			},
			static function (StockPosition $stockPosition): SvgIcon {
				if ($stockPosition->isPositionClosed()) {
					return SvgIcon::CHECK_CIRCLE;
				}

				return SvgIcon::EYE;
			},
		);

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

		$grid->addColumnText(
			'pricePerPieceFinal',
			'Konečná cena za kus',
			static fn (StockPosition $stockPosition): float|null => $stockPosition->getStockClosedPosition()?->getClosePricePerPiece()->getPrice(),
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

		$grid->addColumnText(
			'totalFinalInvestedAmount',
			'Konečná částka v měně brokera',
			function (StockPosition $stockPosition): string {
				if ($stockPosition->getStockClosedPosition() !== null) {
					return $this->assetPriceRenderer->getGridAssetPriceValue(
						$stockPosition->getStockClosedPosition()->getTotalCloseAmountInBrokerCurrency(),
					);
				}

				return Datagrid::NULLABLE_PLACEHOLDER;
			},
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

		$grid->addAction(
			'closePosition',
			'Uzavřít pozici',
			'StockPositionEdit:closePosition',
			[
				new DatagridActionParameter('stockPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::EMERALD,
		)->setConditionCallback(
			static fn (StockPosition $stockPosition): bool => $stockPosition->isPositionClosed() === false
		);

		$grid->addAction(
			'closePosition',
			'Uzavřít pozici',
			'StockPositionEdit:closePosition',
			[
				new DatagridActionParameter('stockPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		)->setConditionCallback(
			static fn (StockPosition $stockPosition): bool => $stockPosition->isPositionClosed() !== false
		);

		$grid->setRowRender(
			new BaseRowRenderer(
				static function (StockPosition $stockPosition): string {
					if ($stockPosition->isPositionClosed()) {
						return 'bg-emerald-100';
					}

					return 'bg-gray-100';
				},
			),
		);

		return $grid;
	}

}
