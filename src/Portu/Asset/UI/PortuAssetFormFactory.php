<?php

declare(strict_types = 1);

namespace App\Portu\Asset\UI;

use App\Currency\CurrencyEnum;
use App\Portu\Asset\PortuAsset;
use App\Portu\Asset\PortuAssetFacade;
use App\Portu\Asset\PortuAssetRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class PortuAssetFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private PortuAssetFacade $portuAssetFacade,
		private PortuAssetRepository $portuAssetRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('name', 'Název portfolia')
			->setRequired();

		$form->addSelect(
			'currency',
			'Měna',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->onSuccess[] = function (Form $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->portuAssetFacade->update(
					$id,
					TypeValidator::validateString($values->name),
					CurrencyEnum::from(TypeValidator::validateString($values->currency)),
				);
			} else {
				$this->portuAssetFacade->create(
					TypeValidator::validateString($values->name),
					CurrencyEnum::from(TypeValidator::validateString($values->currency)),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->portuAssetRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(Form $form, PortuAsset $portuAsset): void
	{
		$form->setDefaults([
			'name' => $portuAsset->getName(),
			'currency' => $portuAsset->getCurrency()->value,
		]);
	}

}
