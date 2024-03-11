<?php

declare(strict_types = 1);

namespace App\Currency\UI;

use App\UI\Base\BaseAdminPresenter;

class CurrencyOverviewPresenter extends BaseAdminPresenter
{

	public function renderDefault(): void
	{
		$this->template->heading = 'Měnový přehled';

		$this->template->czkValuesToConvert = [
			1000,
			5000,
			10000,
			15000,
			20000,
			25000,
			30000,
			35000,
			40000,
			45000,
			50000,
		];

		$this->template->eurValuesToConvert = [
			100,
			500,
			1000,
			1500,
			2000,
			2500,
			3000,
			3500,
			4000,
			4500,
			5000,
		];

		$this->template->usdValuesToConvert = [
			100,
			500,
			1000,
			1500,
			2000,
			2500,
			3000,
			3500,
			4000,
			4500,
			5000,
		];

		$this->template->gbpValuesToConvert = [
			100,
			500,
			1000,
			1500,
			2000,
			2500,
			3000,
			3500,
			4000,
			4500,
			5000,
		];
	}

}
