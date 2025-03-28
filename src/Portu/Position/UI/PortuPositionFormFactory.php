<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Portu\Position\PortuPosition;
use App\Portu\Position\PortuPositionFacade;
use App\Portu\Position\PortuPositionRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class PortuPositionFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly PortuPositionFacade $portuPositionFacade,
		private readonly PortuPositionRepository $portuPositionRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, UuidInterface $portuAssetId, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addDatePicker('startDate', 'Datum vytvoření portfolia');

		$form->addText(
			'startInvestmentPrice',
			'Úvodní investice',
		)->setRequired()->addRule(Form::Float);

		$form->addText(
			'monthlyIncreasePrice',
			'Měsíční pravidelná investice',
		)->setRequired()->addRule(Form::Float);

		$form->addText(
			'currentValuePrice',
			'Aktuální hodnota',
		)->setRequired()->addRule(Form::Float);

		$form->addText(
			'totalInvestedToThisDatePrice',
			'Aktuálně zainvestovaná částka',
		)->setRequired()->addRule(Form::Float);

		$form->onSuccess[] = function (Form $form) use ($id, $portuAssetId, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->portuPositionFacade->update(
					$id,
					$portuAssetId,
					TypeValidator::validateImmutableDatetime($values->startDate),
					TypeValidator::validateFloat($values->startInvestmentPrice),
					TypeValidator::validateFloat($values->monthlyIncreasePrice),
					TypeValidator::validateFloat($values->currentValuePrice),
					TypeValidator::validateFloat($values->totalInvestedToThisDatePrice),
				);
			} else {
				$this->portuPositionFacade->create(
					$portuAssetId,
					TypeValidator::validateImmutableDatetime($values->startDate),
					TypeValidator::validateFloat($values->startInvestmentPrice),
					TypeValidator::validateFloat($values->monthlyIncreasePrice),
					TypeValidator::validateFloat($values->currentValuePrice),
					TypeValidator::validateFloat($values->totalInvestedToThisDatePrice),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->portuPositionRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(Form $form, PortuPosition $portuPosition): void
	{
		$form->setDefaults([
			'startDate' => $portuPosition->getStartDate(),
			'startInvestmentPrice' => $portuPosition->getStartInvestment()->getPrice(),
			'monthlyIncreasePrice' => $portuPosition->getMonthlyIncrease()->getPrice(),
			'currentValuePrice' => $portuPosition->getCurrentValue()->getPrice(),
			'totalInvestedToThisDatePrice' => $portuPosition->getTotalInvestedToThisDate()->getPrice(),
		]);
	}

}
