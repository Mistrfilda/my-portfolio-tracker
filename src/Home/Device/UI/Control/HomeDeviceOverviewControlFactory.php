<?php

declare(strict_types = 1);

namespace App\Home\Device\UI\Control;

use App\Home\Home;

interface HomeDeviceOverviewControlFactory
{

	public function create(Home $home): HomeDeviceOverviewControl;

}
