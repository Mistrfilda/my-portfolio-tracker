<?php

declare(strict_types = 1);

namespace App\Stock\Position\UI;

use App\Asset\Price\AssetPriceRenderer;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\PriceDiff;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Column\ColumnAlignmentEnum;
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
		$grid->enableColumnSelection();
		$grid->setCompact();
		$grid->setActionsInDropdown();

		$stockAsset = $grid->addColumnText(
			'stockAsset',
			'Akcie',
			static fn (StockPosition $stockPosition): string => $stockPosition->getAsset()->getName(),
			'stockAsset.name',
		);

		$stockAsset->setFilterText();
		$stockAsset->setSortable();

		$orderDate = $grid->addColumnDate('orderDate', 'Datum nákupu');
		$orderDate->setFilterDateRange();
		$orderDate->setSortable();

		$grid->addColumnDate(
			'closedDate',
			'Uzavřena dne',
			static fn (StockPosition $stockPosition): ImmutableDateTime|null => $stockPosition->getStockClosedPosition()?->getDate(),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false);

		$status = $grid->addColumnBadge(
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
		$status->setAlignment(ColumnAlignmentEnum::CENTER);

		$grid->addFilterNullState(
			'status',
			'Stav pozice',
			'stockClosedPosition',
			'Otevřené',
			'Uzavřené',
		);

		$grid->addColumnBadge(
			'orderPiecesCount',
			'Počet kusů',
			TailwindColorConstant::BLUE,
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$pricePerPiece = $grid->addColumnText(
			'pricePerPiece',
			'Cena za kus',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getPricePerPiece(),
			),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'pricePerPieceFinal',
			'Konečná cena za kus',
			static fn (StockPosition $stockPosition): float|null => $stockPosition->getStockClosedPosition()?->getClosePricePerPiece()->getPrice(),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$pricePerPiece->setSortable();

		$currency = $grid->addColumnText(
			'currency',
			'Měna',
			static fn (StockPosition $stockPosition): string => $stockPosition->getAsset()->getCurrency()->format(),
			'stockAsset.currency',
		);
		$currency->setFilterSelect(CurrencyEnum::getOptionsForAdminSelect());
		$currency->setSortable();
		$currency->setDefaultVisible(false)->setMobileVisible(false);

		$grid->addColumnText(
			'totalInvestedAmount',
			'Celková investovaná částka',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getTotalInvestedAmount(),
			),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'totalInvestedAmountBroker',
			'Celková investovaná částka v měně brokera',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getTotalInvestedAmountInBrokerCurrency(),
			),
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'currentTotalAmount',
			'Aktuální hodnota pozice',
			fn (StockPosition $stockPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$stockPosition->getCurrentTotalAmount(),
			),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

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
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$summaryPriceCallback = fn (StockPosition $stockPosition): PriceDiff => $this->assetPriceService->getAssetPriceDiff(
			$this->currencyConversionFacade->getConvertedAssetPrice(
				$stockPosition->getCurrentTotalAmount(),
				$stockPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency(),
			),
			$stockPosition->getTotalInvestedAmountInBrokerCurrency(),
		);

		$summaryPrice = $grid->addColumnBadge(
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
		$summaryPrice->setAlignment(ColumnAlignmentEnum::RIGHT);

		$summaryPricePercentage = $grid->addColumnBadge(
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
		$summaryPricePercentage->setAlignment(ColumnAlignmentEnum::RIGHT);

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
			SvgIcon::SCISSORS,
			TailwindColorConstant::EMERALD,
		)->setConditionCallback(
			static fn (StockPosition $stockPosition): bool => $stockPosition->isPositionClosed() === false,
		);

		$grid->addAction(
			'editClosedPosition',
			'Upravit uzavření',
			'StockPositionEdit:closePosition',
			[
				new DatagridActionParameter('stockPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		)->setConditionCallback(
			static fn (StockPosition $stockPosition): bool => $stockPosition->isPositionClosed() !== false,
		);

		$grid->setRowRender(
			new BaseRowRenderer(
				static function (StockPosition $stockPosition): string {
					if ($stockPosition->isPositionClosed()) {
						return 'bg-gray-50';
					}

					return 'bg-white';
				},
			),
		);

		return $grid;
	}

}
