<?php

declare(strict_types = 1);

namespace App\Crypto\Position\UI;

use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class CryptoPositionPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly CryptoPositionGridFactory $cryptoPositionGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Kryptoměnové pozice';
	}

	protected function createComponentCryptoPositionGrid(): Datagrid
	{
		return $this->cryptoPositionGridFactory->create();
	}

}
