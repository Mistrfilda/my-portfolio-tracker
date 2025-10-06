<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry\UI;

use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\Industry\StockAssetIndustryFacade;
use App\Stock\Asset\Industry\StockAssetIndustryRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockAssetIndustryFormFactory
{

	public function __construct(
		private StockAssetIndustryRepository $stockAssetIndustryRepository,
		private StockAssetIndustryFacade $stockAssetIndustryFacade,
		private AdminFormFactory $adminFormFactory,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->addText('name', 'Jméno');
		$form->addText('mappingName', 'Jméno pro mapovaní');
		$form->addFloat('currentPERatio', 'Aktuální P/E ratio');
		$form->addSubmit('submit', 'Uložit');

		if ($id !== null) {
			$this->setDefaults($form, $this->stockAssetIndustryRepository->getById($id));
		}

		$form->onSuccess[] = function (AdminForm $form) use ($id, $onSuccess): void {
			$values = $form->getValues();
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->stockAssetIndustryFacade->update(
					$id,
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->mappingName),
					TypeValidator::validateFloat($values->currentPERatio),
				);
			} else {
				$this->stockAssetIndustryFacade->create(
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->mappingName),
					TypeValidator::validateFloat($values->currentPERatio),
				);
			}

			$onSuccess($id);
		};

		return $form;
	}

	public function setDefaults(AdminForm $form, StockAssetIndustry $stockAssetIndustry): void
	{
		$form->setDefaults([
			'name' => $stockAssetIndustry->getName(),
			'mappingName' => $stockAssetIndustry->getMappingName(),
			'currentPERatio' => $stockAssetIndustry->getCurrentPERatio(),
		]);
	}

}
