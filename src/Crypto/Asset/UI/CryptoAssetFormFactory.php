<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI;

use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetFacade;
use App\Crypto\Asset\CryptoAssetRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\UI\Icon\SvgIcon;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class CryptoAssetFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private CryptoAssetFacade $cryptoAssetFacade,
		private CryptoAssetRepository $cryptoAssetRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('name', 'Název kryptoměny')
			->setRequired();

		$form->addText('ticker', 'Ticker')
			->setRequired();

		$form->addSelect('svgIcon', 'Ikona', [
			SvgIcon::CRYPTO_BITCOIN->value => SvgIcon::CRYPTO_BITCOIN->value,
			SvgIcon::CRYPTO_ETHEREUM->value => SvgIcon::CRYPTO_ETHEREUM->value,
			SvgIcon::CRYPTO_CARDANO->value => SvgIcon::CRYPTO_CARDANO->value,
			SvgIcon::CRYPTO_SOLANA->value => SvgIcon::CRYPTO_SOLANA->value,
		])->setRequired()->setPrompt(AdminForm::SELECT_PLACEHOLDER);

		$form->onSuccess[] = function (Form $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->cryptoAssetFacade->update(
					$id,
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->ticker),
					SvgIcon::from(TypeValidator::validateString($values->svgIcon)),
				);
			} else {
				$this->cryptoAssetFacade->create(
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->ticker),
					SvgIcon::from(TypeValidator::validateString($values->svgIcon)),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->cryptoAssetRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(Form $form, CryptoAsset $cryptoAsset): void
	{
		$form->setDefaults([
			'name' => $cryptoAsset->getName(),
			'ticker' => $cryptoAsset->getTicker(),
			'svgIcon' => $cryptoAsset->getSvgIcon()->value,
		]);
	}

}
