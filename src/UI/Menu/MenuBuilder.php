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
				'PortfolioStatistic',
				'default',
				SvgIcon::TABLE_CELLS,
				'Statistiky',
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
				'StockAssetDetail',
				'default',
				SvgIcon::ADJUSTMENTS,
				'Detail akciových pozic',
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
		];
	}

}
