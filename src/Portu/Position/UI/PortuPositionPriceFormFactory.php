<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Portu\Position\PortuPositionFacade;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class PortuPositionPriceFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private PortuPositionFacade $portuPositionFacade,
	)
	{
	}

	public function create(UuidInterface $portuPositionId, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addDatePicker('date', 'Datum');

		$form->addText(
			'currentValuePrice',
			'Aktuální hodnota',
		)->setRequired()->addRule(Form::FLOAT);

		$form->addText(
			'totalInvestedToThisDatePrice',
			'Aktuálně zainvestovaná částka',
		)->setRequired()->addRule(Form::FLOAT);

		$form->addCheckbox('shouldUpdateWholePosition', 'Aktualizovat cenu celého portfolia');

		$form->onSuccess[] = function (Form $form) use ($portuPositionId, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$this->portuPositionFacade->updatePriceForDate(
				$portuPositionId,
				$values->date,
				$values->currentValuePrice,
				$values->totalInvestedToThisDatePrice,
				$values->shouldUpdateWholePosition,
			);

			$onSuccess();
		};

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

}
