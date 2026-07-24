<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Asset\Price\AssetPriceRenderer;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\PriceDiff;
use App\Currency\CurrencyConversionFacade;
use App\Portu\Position\PortuPosition;
use App\Portu\Position\PortuPositionRepository;
use App\UI\Control\Datagrid\Action\DatagridActionParameter;
use App\UI\Control\Datagrid\Column\ColumnAlignmentEnum;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Datagrid\DatagridFactory;
use App\UI\Control\Datagrid\Datasource\DoctrineDataSource;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use Ramsey\Uuid\UuidInterface;

class PortuPositionGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly PortuPositionRepository $portuPositionRepository,
		private readonly AssetPriceRenderer $assetPriceRenderer,
		private readonly AssetPriceService $assetPriceService,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function create(UuidInterface $portuAssetId): Datagrid
	{
		$grid = $this->datagridFactory->create(
			new DoctrineDataSource(
				$this->portuPositionRepository->createQueryBuilderForDatagrid($portuAssetId),
			),
		);

		$grid->setLimit(30);
		$grid->enableColumnSelection();
		$grid->setCompact();
		$grid->setActionsInDropdown();

		$grid->addColumnDate('startDate', 'Datum vzniku')
			->setSortable();

		$pricePerPiece = $grid->addColumnText(
			'startInvestment',
			'Úvodní vklad',
			fn (PortuPosition $portuPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$portuPosition->getStartInvestment(),
			),
		);
		$pricePerPiece
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);
		$pricePerPiece->setSortable();

		$grid->addColumnText(
			'monthlyIncrease',
			'Měsíční vklad',
			fn (PortuPosition $portuPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$portuPosition->getMonthlyIncrease(),
			),
		)
			->setDefaultVisible(false)
			->setMobileVisible(false)
			->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'totalInvestedToThisDate',
			'Celková investovaná částka',
			fn (PortuPosition $portuPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$portuPosition->getTotalInvestedAmount(),
			),
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addColumnText(
			'currentValue',
			'Aktuální hodnota pozice',
			fn (PortuPosition $portuPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$portuPosition->getCurrentTotalAmount(),
			),
		)->setAlignment(ColumnAlignmentEnum::RIGHT);

		$summaryPriceCallback = fn (PortuPosition $portuPosition): PriceDiff => $this->assetPriceService->getAssetPriceDiff(
			$this->currencyConversionFacade->getConvertedAssetPrice(
				$portuPosition->getCurrentTotalAmount(),
				$portuPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency(),
			),
			$portuPosition->getTotalInvestedAmountInBrokerCurrency(),
		);

		$summaryPrice = $grid->addColumnBadge(
			'summaryPrice',
			'Zisk/ztráta',
			TailwindColorConstant::GREEN,
			static function (PortuPosition $portuPosition) use ($summaryPriceCallback): string {
				$priceDiff = $summaryPriceCallback($portuPosition);

				return CurrencyFilter::format($priceDiff->getPriceDifference(), $priceDiff->getCurrencyEnum());
			},
			static fn (PortuPosition $portuPosition): string => $summaryPriceCallback($portuPosition)->getTrend()->getTailwindColor(),
			static fn (PortuPosition $portuPosition): SvgIcon => $summaryPriceCallback($portuPosition)->getTrend()->getSvgIcon(),
		);
		$summaryPrice->setAlignment(ColumnAlignmentEnum::RIGHT);

		$summaryPricePercentage = $grid->addColumnBadge(
			'summaryPricePercentage',
			'Zisk/ztráta v %',
			TailwindColorConstant::GREEN,
			static function (PortuPosition $portuPosition) use ($summaryPriceCallback): string {
				$priceDiff = $summaryPriceCallback($portuPosition);

				return PercentageFilter::format($priceDiff->getPercentageDifference());
			},
			static fn (PortuPosition $portuPosition): string => $summaryPriceCallback($portuPosition)->getTrend()->getTailwindColor(),
			static fn (PortuPosition $portuPosition): SvgIcon => $summaryPriceCallback($portuPosition)->getTrend()->getSvgIcon(),
		);
		$summaryPricePercentage->setAlignment(ColumnAlignmentEnum::RIGHT);

		$grid->addAction(
			'edit',
			'Editovat',
			'PortuPositionEdit:edit',
			[
				new DatagridActionParameter('portuAssetId', 'portuAssetId'),
				new DatagridActionParameter('portuPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'prices',
			'Hodnoty portfolia',
			'PortuPositionPrice:prices',
			[
				new DatagridActionParameter('portuPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::EMERALD,
		);

		return $grid;
	}

}
