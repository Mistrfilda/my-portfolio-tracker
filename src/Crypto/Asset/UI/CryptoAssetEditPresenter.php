<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI;

use App\Crypto\Asset\CryptoAssetRepository;
use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class CryptoAssetEditPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly CryptoAssetRepository $cryptoAssetRepository,
		private readonly CryptoAssetFormFactory $cryptoAssetFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(string|null $id): void
	{
		if ($id !== null) {
			$cryptoAsset = $this->cryptoAssetRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf('Úprava crypto %s', $cryptoAsset->getName());
		} else {
			$this->template->heading = 'Přidání nové crypto';
		}
	}

	protected function createComponentCryptoAssetForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Kryptoměna úspěšně vytvořena', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Kryptoměna úspěšně upravena', FlashMessageType::SUCCESS);
			}

			$this->redirect('CryptoAsset:default');
		};

		return $this->cryptoAssetFormFactory->create($id, $onSuccess);
	}

}
