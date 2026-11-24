<?php

declare(strict_types = 1);

namespace App\Tax\UI;

use App\Tax\TaxYearProfileFacade;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\UI\Control\Form\Input\DatePickerInput;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use function assert;

class TaxYearDonationFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private TaxYearProfileFacade $profileFacade,
	)
	{
	}

	public function create(int $year, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('recipientName', 'Příjemce / portál')
			->setRequired('Zadejte název organizace nebo portálu (např. Darujme.cz, Donio.cz).')
			->setHtmlAttribute('placeholder', 'Např. Darujme.cz – Člověk v tísni');

		$form->addInteger('amountCzk', 'Částka daru (Kč)')
			->setRequired('Zadejte částku daru v celých korunách.')
			->addRule(Form::Min, 'Částka daru musí být kladná.', 1);

		$form->addDatePicker('donatedAt', 'Datum daru (volitelné)')
			->setNullable()
			->setRequired(false)
			->addCondition(Form::Filled)
			->addRule(
				static function (DatePickerInput $input) use ($year): bool {
					$value = $input->getValue();

					return $value instanceof ImmutableDateTime && $value->getYear() === $year;
				},
				sprintf('Datum daru musí spadat do daňového roku %d.', $year),
			);

		$form->addText('note', 'Poznámka / číslo potvrzení (volitelné)')
			->setNullable()
			->setRequired(false);

		$form->addSubmit('submit', 'Přidat dar');

		$form->onSuccess[] = function (AdminForm $form) use ($year, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$this->profileFacade->addDonation(
				year: $year,
				recipientName: TypeValidator::validateString($values->recipientName),
				amountCzk: TypeValidator::validateInt($values->amountCzk),
				donatedAt: TypeValidator::validateNullableImmutableDatetime($values->donatedAt),
				note: TypeValidator::validateNullableString($values->note),
			);

			$onSuccess();
		};

		return $form;
	}

}
