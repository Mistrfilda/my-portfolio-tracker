<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI;

use App\Crypto\Asset\UI\Detail\List\CryptoAssetListDetailControl;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListDetailControlEnum;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListDetailControlFactory;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListSummaryDetailControl;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListSummaryDetailControlFactory;
use App\UI\Base\BaseAdminPresenter;

class CryptoAssetPositionDetailPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly CryptoAssetListDetailControlFactory $cryptoPositionDetailControlFactory,
		private readonly CryptoAssetListSummaryDetailControlFactory $cryptoAssetSummaryDetailControlFactory,
	)
	{
		parent::__construct();
	}

	/**
	 * @param array<string> $ids
	 */
	public function renderDefault(array $ids = []): void
	{
		$this->template->heading = 'Detaily akciových pozic';
	}

	protected function createComponentCryptoAssetSummaryDetailControl(): CryptoAssetListSummaryDetailControl
	{
		return $this->cryptoAssetSummaryDetailControlFactory->create(
			[],
			CryptoAssetListDetailControlEnum::OPEN_POSITIONS,
		);
	}

	protected function createComponentCryptoPositionDetailControl(): CryptoAssetListDetailControl
	{
		return $this->cryptoPositionDetailControlFactory->create([], CryptoAssetListDetailControlEnum::OPEN_POSITIONS);
	}

}
