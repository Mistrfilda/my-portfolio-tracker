<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail;

use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Asset\UI\Detail\Control\CryptoAssetDetailControl;
use App\Crypto\Asset\UI\Detail\Control\CryptoAssetDetailControlFactory;
use App\UI\Base\BaseSysadminPresenter;
use Nette\Application\Attributes\Persistent;

class CryptoAssetDetailPresenter extends BaseSysadminPresenter
{

	#[Persistent]
	public int $currentChartDays = 90;

	public function __construct(
		private CryptoAssetRepository $cryptoAssetRepository,
		private CryptoAssetDetailControlFactory $cryptoAssetDetailControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDetail(string $id, int $currentChartDays = 120): void
	{
		$cryptoAsset = $this->cryptoAssetRepository->getById($this->processParameterRequiredUuid());
		$this->template->cryptoAsset = $cryptoAsset;
		$this->template->heading = $cryptoAsset->getName();
	}

	public function handleChangeDays(int $currentChartDays): void
	{
		$this->currentChartDays = $currentChartDays;
		$this->redrawControl();
	}

	protected function createComponentCryptoAssetDetailControl(): CryptoAssetDetailControl
	{
		return $this->cryptoAssetDetailControlFactory->create(
			$this->processParameterRequiredUuid(),
			$this->processParameterInt('currentChartDays') ?? 90,
		);
	}

}
