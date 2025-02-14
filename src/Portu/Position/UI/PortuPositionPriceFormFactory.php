<?php

declare(strict_types = 1);

namespace App\Portu\Position\UI;

use App\Portu\Position\PortuPositionFacade;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class PortuPositionPriceFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly PortuPositionFacade $portuPositionFacade,
		private readonly PortuAssetPriceRecordRepository $portuAssetPriceRecordRepository,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(
		UuidInterface $portuPositionId,
		int|null $previousPortuPositionPrice,
		callable $onSuccess,
	): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addDatePicker('date', 'Datum');

		$form->addText(
			'currentValuePrice',
			'Aktuální hodnota',
		)->setRequired()->addRule(Form::Float);

		$form->addText(
			'totalInvestedToThisDatePrice',
			'Aktuálně zainvestovaná částka',
		)->setRequired()->addRule(Form::Float);

		$form
			->addCheckbox('shouldUpdateWholePosition', 'Aktualizovat cenu celého portfolia')
			->setDefaultValue(true);

		if ($previousPortuPositionPrice !== null) {
			$this->setDefaults(
				$this->portuAssetPriceRecordRepository->getById($previousPortuPositionPrice),
				$form,
			);
		}

		$form->onSuccess[] = function (Form $form) use ($portuPositionId, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$this->portuPositionFacade->updatePriceForDate(
				$portuPositionId,
				TypeValidator::validateImmutableDatetime($values->date),
				TypeValidator::validateFloat($values->currentValuePrice),
				TypeValidator::validateFloat($values->totalInvestedToThisDatePrice),
				TypeValidator::validateBool($values->shouldUpdateWholePosition),
			);

			$onSuccess();
		};

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(PortuAssetPriceRecord $previousPriceRecord, AdminForm $form): void
	{
		$defaults = [
			'date' => $this->datetimeFactory->createToday(),
			'currentValuePrice' => $previousPriceRecord->getCurrentValueAssetPrice()->getPrice(),
			'totalInvestedToThisDatePrice' => $previousPriceRecord->getTotalInvestedAmountAssetPrice()->getPrice(),
		];

		$form->setDefaults($defaults);
	}

}
