<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Currency\CurrencyEnum;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendFormFactory
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetDividendFacade $stockAssetDividendFacade,
		private AdminFormFactory $adminFormFactory,
	)
	{
	}

	public function create(UuidInterface|null $id, UuidInterface $stockAssetId, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addDatePicker('exDate', 'Ex date')
			->setRequired();

		$form->addDatePicker('paymentDate', 'Datum výplaty')
			->setRequired();

		$form->addDatePicker('declarationDate', 'Datum deklarace')
			->setRequired();

		$form->addText('amount', 'Částka')
			->addRule(Form::Float)
			->setRequired();

		$form->addSelect(
			'currency',
			'Měna',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->addSelect('dividendType', 'Typ dividendy', StockAssetDividendTypeEnum::getOptionsForAdminSelect())
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		if ($id !== null) {
			$this->setDefaults(
				$form,
				$this->stockAssetDividendRepository->getById($id),
			);
		}

		$form->addSubmit('submit', 'Uložit');

		$form->onSuccess[] = function (Form $form) use ($id, $stockAssetId, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->stockAssetDividendFacade->update(
					$id,
					TypeValidator::validateImmutableDatetime($values->exDate),
					TypeValidator::validateImmutableDatetime($values->paymentDate),
					TypeValidator::validateNullableImmutableDatetime($values->declarationDate),
					CurrencyEnum::from(TypeValidator::validateString($values->currency)),
					TypeValidator::validateFloat($values->amount),
					StockAssetDividendTypeEnum::from(TypeValidator::validateString($values->dividendType)),
				);
			} else {
				$this->stockAssetDividendFacade->create(
					$stockAssetId,
					TypeValidator::validateImmutableDatetime($values->exDate),
					TypeValidator::validateImmutableDatetime($values->paymentDate),
					TypeValidator::validateNullableImmutableDatetime($values->declarationDate),
					CurrencyEnum::from(TypeValidator::validateString($values->currency)),
					TypeValidator::validateFloat($values->amount),
					StockAssetDividendTypeEnum::from(TypeValidator::validateString($values->dividendType)),
				);
			}

			$onSuccess();
		};

		return $form;
	}

	private function setDefaults(AdminForm $form, StockAssetDividend $stockAssetDividend): void
	{
		$form->setDefaults([
			'exDate' => $stockAssetDividend->getExDate(),
			'paymentDate' => $stockAssetDividend->getPaymentDate(),
			'declarationDate' => $stockAssetDividend->getDeclarationDate(),
			'currency' => $stockAssetDividend->getCurrency()->value,
			'amount' => $stockAssetDividend->getAmount(),
			'dividendType' => $stockAssetDividend->getDividendType()->value,
		]);
	}

}
