<?php

declare(strict_types = 1);

namespace App\Dashboard;

enum DashboardValueGroupEnum: string
{

	case CURRENCY = 'currency';

	case TOTAL_VALUES = 'total_values';

	case STOCKS = 'stocks';

	case PORTU = 'portu';

	public function heading(): string
	{
		return match ($this) {
			DashboardValueGroupEnum::CURRENCY => 'Kurzy měn',
			DashboardValueGroupEnum::TOTAL_VALUES => 'Celkové hodnoty portfolia',
			DashboardValueGroupEnum::STOCKS => 'Akcie',
			DashboardValueGroupEnum::PORTU => 'Portu',
		};
	}

	public function description(): string|null
	{
		return match ($this) {
			DashboardValueGroupEnum::CURRENCY => 'Aktuální kurzy měn',
			DashboardValueGroupEnum::TOTAL_VALUES => null,
			DashboardValueGroupEnum::STOCKS => null,
			DashboardValueGroupEnum::PORTU => null,
		};
	}

}
