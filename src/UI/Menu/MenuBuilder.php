<?php

declare(strict_types = 1);

namespace App\UI\Menu;

use App\Stock\Dividend\StockAssetDividendRepository;
use App\UI\Icon\SvgIcon;
use Mistrfilda\Datetime\DatetimeFactory;

class MenuBuilder
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @return array<MenuItem>
	 */
	public function buildMenu(): array
	{
		return [
			new MenuItem('Dashboard', 'default', SvgIcon::HOME, 'Dashboard'),
			new MenuItem(
				'PortfolioStatisticChart',
				'default',
				SvgIcon::CHART_BAR_SQUARE,
				'Grafy',
			),
			new MenuItem(
				'PortfolioStatisticTotal',
				'default',
				SvgIcon::ARCHIVE_BOX,
				'Statistiky',
			),
			new MenuItem(
				'CurrencyOverview',
				'default',
				SvgIcon::CIRCLE_STACK,
				'Měnový přehled',
			),
			new MenuItem(
				'PortfolioStatisticRecord',
				'default',
				SvgIcon::TABLE_CELLS,
				'Uložené statistiky',
			),
			new MenuItem(
				'AppAdmin',
				'default',
				SvgIcon::USERS,
				'Uživatelé',
				['AppAdminEdit'],
				true,
			),
			new MenuItem(
				'StockAsset',
				'default',
				SvgIcon::COLLECTION,
				'Akcie',
				[
					'StockAssetEdit',
					'StockAssetDividend',
				],
			),
			new MenuItem(
				'StockPosition',
				'default',
				SvgIcon::DOCUMENT_DUPLICATE,
				'Akciové pozice',
				[
					'StockPosition',
					'StockPositionEdit',
				],
			),
			new MenuItem(
				'StockAssetPositionDetail',
				'default',
				SvgIcon::ADJUSTMENTS,
				'Otevřené akciové pozice',
				[
					'StockAssetDetail',
				],
			),
			new MenuItem(
				'StockAssetClosedPositionDetail',
				'default',
				SvgIcon::ADJUSTMENTS,
				'Zavřené akciové pozice',
				[],
			),
			new MenuItem(
				'StockAssetDividendDetail',
				'default',
				SvgIcon::ARROW_TRENDING_UP,
				'Dividendy',
				[],
				badge: (string) $this->stockAssetDividendRepository->getCountSinceDate(
					$this->datetimeFactory->createNow()->deductMonthsFromDatetime(1),
				),
			),
			new MenuItem(
				'StockAssetDividendRecord',
				'default',
				SvgIcon::ARROW_TRENDING_UP,
				'Vyplacené dividendy',
				[],
			),
			new MenuItem(
				'PortuAsset',
				'default',
				SvgIcon::PORTU,
				'Portu portfolia',
				[
					'PortuPosition',
					'PortuPositionEdit',
					'PortuAssetEdit',
					'PortuPositionPrice',
				],
			),
			new MenuItem(
				'ExpenseOverview',
				'default',
				SvgIcon::BANKNOTES,
				'Přehled výdajů',
				[],
			),
			new MenuItem(
				'Expense',
				'kb',
				SvgIcon::CREDIT_CARD,
				'Výdaje',
				[],
			),
			new MenuItem(
				'ExpenseTag',
				'tags',
				SvgIcon::TAG,
				'Výdajové tagy',
				[],
			),
			new MenuItem(
				'WorkMonthlyIncome',
				'default',
				SvgIcon::COLLECTION,
				'Přehled příjmů z práce',
				[],
			),
			new MenuItem(
				'BankIncome',
				'default',
				SvgIcon::COLLECTION,
				'Přehled příjmů z banky',
				[],
			),
		];
	}

}
