<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\Kb\KbCashFacade;
use App\Cash\Bank\Kb\KbSourceEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use InvalidArgumentException;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\ArrayHash;
use function assert;

class BankExpenseUploadFormFactory
{

	public function __construct(
		private KbCashFacade $kbExpenseFacade,
		private AdminFormFactory $adminFormFactory,
	)
	{
	}

	public function create(BankSourceEnum $bankSource, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addSelect('source', 'Typ souboru', KbSourceEnum::getSelectOptions());

		$form->addUpload('file', 'Nahrajte soubor')
			->setRequired();

		$form->onSuccess[] = function (Form $form) use ($onSuccess, $bankSource): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$file = $values->file;
			assert($file instanceof FileUpload);

			$contents = $file->getContents();
			if ($contents === null) {
				$form->addError('Nepodařilo se nahrát soubor');
				return;
			}

			match ($bankSource) {
				BankSourceEnum::KOMERCNI_BANKA => $onSuccess($this->kbExpenseFacade->processFileContents(
					$contents,
					KbSourceEnum::from(TypeValidator::validateString($values->source)),
				))
			};

			throw new InvalidArgumentException();
		};

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

}
