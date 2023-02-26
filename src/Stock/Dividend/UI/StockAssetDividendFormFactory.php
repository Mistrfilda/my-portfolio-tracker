<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Currency\CurrencyEnum;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
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
			->addRule(Form::FLOAT)
			->setRequired();

		$form->addSelect(
			'currency',
			'Měna',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
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
					$values->exDate,
					$values->paymentDate,
					$values->declarationDate,
					CurrencyEnum::from($values->currency),
					$values->amount,
				);
			} else {
				$this->stockAssetDividendFacade->create(
					$stockAssetId,
					$values->exDate,
					$values->paymentDate,
					$values->declarationDate,
					CurrencyEnum::from($values->currency),
					$values->amount,
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
		]);
	}

}
