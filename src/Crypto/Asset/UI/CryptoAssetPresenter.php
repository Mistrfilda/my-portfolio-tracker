<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI;

use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class CryptoAssetPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly CryptoAssetGridFactory $cryptoAssetGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Kryptoměny';
	}

	protected function createComponentCryptoAssetGrid(): Datagrid
	{
		return $this->cryptoAssetGridFactory->create();
	}

}
