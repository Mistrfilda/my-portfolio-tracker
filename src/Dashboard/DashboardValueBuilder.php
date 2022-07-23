<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Admin\AppAdminRepository;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class DashboardValueBuilder
{

	public function __construct(private AppAdminRepository $appAdminRepository,)
	{

	}

	/**
	 * @return array<int, DashboardValue>
	 */
	public function buildValues(): array
	{
		return [
			new DashboardValue(
				'Celkový počet uživatelů',
				(string) $this->appAdminRepository->getCount(),
				TailwindColorConstant::BLUE,
				SvgIcon::GIFT,
			),
		];
	}

}
