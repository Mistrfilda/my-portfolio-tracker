<?php

declare(strict_types = 1);

namespace App\Crypto\Position\UI;

use App\Asset\Price\AssetPriceRenderer;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\PriceDiff;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyConversionFacade;
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

class CryptoPositionGridFactory
{

	public function __construct(
		private readonly DatagridFactory $datagridFactory,
		private readonly CryptoPositionRepository $cryptoPositionRepository,
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
				$this->cryptoPositionRepository->createQueryBuilderForDatagrid(),
			),
		);

		$grid->setLimit(30);

		$cryptoAsset = $grid->addColumnText(
			'cryptoAsset',
			'Kryptoměna',
			static fn (CryptoPosition $cryptoPosition): string => $cryptoPosition->getAsset()->getName(),
			'cryptoAsset.name',
		);

		$cryptoAsset->setFilterText();
		$cryptoAsset->setSortable();

		$grid->addColumnDate('orderDate', 'Datum nákupu')
			->setSortable();

		$grid->addColumnDate(
			'closedDate',
			'Uzavřena dne',
			static fn (CryptoPosition $cryptoPosition): ImmutableDateTime|null => $cryptoPosition->getCryptoClosedPosition()?->getDate(),
		);

		$grid->addColumnBadge(
			'isOpen',
			'Stav pozice',
			TailwindColorConstant::GREEN,
			static function (CryptoPosition $cryptoPosition): string {
				if ($cryptoPosition->isPositionClosed()) {
					return 'Uzavřená';
				}

				return 'Otevřená';
			},
			static function (CryptoPosition $cryptoPosition): string {
				if ($cryptoPosition->isPositionClosed()) {
					return TailwindColorConstant::YELLOW;
				}

				return TailwindColorConstant::BLUE;
			},
			static function (CryptoPosition $cryptoPosition): SvgIcon {
				if ($cryptoPosition->isPositionClosed()) {
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
			fn (CryptoPosition $cryptoPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$cryptoPosition->getPricePerPiece(),
			),
		);

		//phpcs:disable
		$grid->addColumnText(
			'pricePerPieceFinal',
			'Konečná cena za kus',
			static fn (CryptoPosition $cryptoPosition): float|null => $cryptoPosition->getCryptoClosedPosition()?->getClosePricePerPiece()->getPrice(),
		);
		//phpcs:enable

		$pricePerPiece->setSortable();

		$grid->addColumnText(
			'currency',
			'Měna',
			static fn (CryptoPosition $cryptoPosition): string => $cryptoPosition->getAsset()->getCurrency()->format(),
			'cryptoAsset.currency',
		)->setSortable();

		$grid->addColumnText(
			'totalInvestedAmount',
			'Celková investovaná částka',
			fn (CryptoPosition $cryptoPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$cryptoPosition->getTotalInvestedAmount(),
			),
		);

		$grid->addColumnText(
			'totalInvestedAmount',
			'Celková investovaná částka v měně brokera',
			fn (CryptoPosition $cryptoPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$cryptoPosition->getTotalInvestedAmountInBrokerCurrency(),
			),
		);

		$grid->addColumnText(
			'currentTotalAmount',
			'Aktuální hodnota pozice',
			fn (CryptoPosition $cryptoPosition): string => $this->assetPriceRenderer->getGridAssetPriceValue(
				$cryptoPosition->getCurrentTotalAmount(),
			),
		);

		$grid->addColumnText(
			'totalFinalInvestedAmount',
			'Konečná částka v měně brokera',
			function (CryptoPosition $cryptoPosition): string {
				if ($cryptoPosition->getCryptoClosedPosition() !== null) {
					return $this->assetPriceRenderer->getGridAssetPriceValue(
						$cryptoPosition->getCryptoClosedPosition()->getTotalCloseAmountInBrokerCurrency(),
					);
				}

				return Datagrid::NULLABLE_PLACEHOLDER;
			},
		);

		$summaryPriceCallback = fn (CryptoPosition $cryptoPosition): PriceDiff => $this->assetPriceService->getAssetPriceDiff(
			$this->currencyConversionFacade->getConvertedAssetPrice(
				$cryptoPosition->getCurrentTotalAmount(),
				$cryptoPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency(),
			),
			$cryptoPosition->getTotalInvestedAmountInBrokerCurrency(),
		);

		$grid->addColumnBadge(
			'summaryPrice',
			'Zisk/ztráta',
			TailwindColorConstant::GREEN,
			static function (CryptoPosition $cryptoPosition) use ($summaryPriceCallback): string {
				$priceDiff = $summaryPriceCallback($cryptoPosition);

				return CurrencyFilter::format($priceDiff->getPriceDifference(), $priceDiff->getCurrencyEnum());
			},
			static fn (CryptoPosition $cryptoPosition): string => $summaryPriceCallback($cryptoPosition)->getTrend()->getTailwindColor(),
			static fn (CryptoPosition $cryptoPosition): SvgIcon => $summaryPriceCallback($cryptoPosition)->getTrend()->getSvgIcon(),
		);

		$grid->addColumnBadge(
			'summaryPricePercentage',
			'Zisk/ztráta v %',
			TailwindColorConstant::GREEN,
			static function (CryptoPosition $cryptoPosition) use ($summaryPriceCallback): string {
				$priceDiff = $summaryPriceCallback($cryptoPosition);

				return PercentageFilter::format($priceDiff->getPercentageDifference());
			},
			static fn (CryptoPosition $cryptoPosition): string => $summaryPriceCallback($cryptoPosition)->getTrend()->getTailwindColor(),
			static fn (CryptoPosition $cryptoPosition): SvgIcon => $summaryPriceCallback($cryptoPosition)->getTrend()->getSvgIcon(),
		);

		$grid->addAction(
			'edit',
			'Editovat',
			'CryptoPositionEdit:default',
			[
				new DatagridActionParameter('id', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		);

		$grid->addAction(
			'closePosition',
			'Uzavřít pozici',
			'CryptoPositionEdit:closePosition',
			[
				new DatagridActionParameter('cryptoPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::EMERALD,
		)->setConditionCallback(
			static fn (CryptoPosition $cryptoPosition): bool => $cryptoPosition->isPositionClosed() === false,
		);

		$grid->addAction(
			'closePosition',
			'Uzavřít pozici',
			'CryptoPositionEdit:closePosition',
			[
				new DatagridActionParameter('cryptoPositionId', 'id'),
			],
			SvgIcon::PENCIL,
			TailwindColorConstant::BLUE,
		)->setConditionCallback(
			static fn (CryptoPosition $cryptoPosition): bool => $cryptoPosition->isPositionClosed() !== false,
		);

		$grid->setRowRender(
			new BaseRowRenderer(
				static function (CryptoPosition $cryptoPosition): string {
					if ($cryptoPosition->isPositionClosed()) {
						return 'bg-emerald-100';
					}

					return 'bg-gray-100';
				},
			),
		);

		return $grid;
	}

}
