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
		$form->addFloat('currentPERatio', 'Aktuální P/E ratio')->setNullable();
		$form->addFloat('marketCap', 'Market cap')->setNullable();
		$form->addFloat('priceToFreeCashFlow', 'P/FCF ratio')->setNullable();
		$form->addFloat('priceToCashFlow', 'P/CF ratio')->setNullable();
		$form->addFloat('priceToBook', 'P/B ratio')->setNullable();
		$form->addFloat('priceToSales', 'P/S ratio')->setNullable();
		$form->addFloat('pegRatio', 'PEG ratio')->setNullable();
		$form->addFloat('forwardPERatio', 'Forward P/E ratio')->setNullable();
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
					TypeValidator::validateNullableFloat($values->currentPERatio),
					TypeValidator::validateNullableFloat($values->marketCap),
					TypeValidator::validateNullableFloat($values->priceToFreeCashFlow),
					TypeValidator::validateNullableFloat($values->priceToCashFlow),
					TypeValidator::validateNullableFloat($values->priceToBook),
					TypeValidator::validateNullableFloat($values->priceToSales),
					TypeValidator::validateNullableFloat($values->pegRatio),
					TypeValidator::validateNullableFloat($values->forwardPERatio),
				);
			} else {
				$this->stockAssetIndustryFacade->create(
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->mappingName),
					TypeValidator::validateNullableFloat($values->currentPERatio),
					TypeValidator::validateNullableFloat($values->marketCap),
					TypeValidator::validateNullableFloat($values->priceToFreeCashFlow),
					TypeValidator::validateNullableFloat($values->priceToCashFlow),
					TypeValidator::validateNullableFloat($values->priceToBook),
					TypeValidator::validateNullableFloat($values->priceToSales),
					TypeValidator::validateNullableFloat($values->pegRatio),
					TypeValidator::validateNullableFloat($values->forwardPERatio),
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
			'marketCap' => $stockAssetIndustry->getMarketCap(),
			'priceToFreeCashFlow' => $stockAssetIndustry->getPriceToFreeCashFlow(),
			'priceToCashFlow' => $stockAssetIndustry->getPriceToCashFlow(),
			'priceToBook' => $stockAssetIndustry->getPriceToBook(),
			'priceToSales' => $stockAssetIndustry->getPriceToSales(),
			'pegRatio' => $stockAssetIndustry->getPegRatio(),
			'forwardPERatio' => $stockAssetIndustry->getForwardPERatio(),
		]);
	}

}
