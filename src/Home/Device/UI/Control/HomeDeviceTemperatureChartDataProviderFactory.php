<?php

declare(strict_types = 1);

namespace App\Home\Device\UI\Control;

use App\Home\Device\HomeDevice;

interface HomeDeviceTemperatureChartDataProviderFactory
{

	public function create(HomeDevice $homeDevice): HomeDeviceTemperatureChartDataProvider;

}
