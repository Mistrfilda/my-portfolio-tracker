<?php

declare(strict_types = 1);

namespace App\UI\Menu;

use App\UI\Icon\SvgIcon;

class MenuBuilder
{

	/**
	 * @return array<MenuItem>
	 */
	public function buildMenu(): array
	{
		return [
			new MenuItem('Dashboard', 'default', SvgIcon::HOME, 'Dashboard'),
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
		];
	}

}
