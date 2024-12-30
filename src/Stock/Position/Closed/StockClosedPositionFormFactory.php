<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockClosedPositionFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockClosedPositionFacade $stockPositionFacade,
		private readonly StockClosedPositionRepository $stockClosedPositionRepository,
		private readonly StockPositionRepository $stockPositionRepository,
	)
	{
	}

	public function create(UuidInterface $stockPositionId, callable $onSuccess): AdminForm
	{
		$stockPosition = $this->stockPositionRepository->getById($stockPositionId);
		$id = $stockPosition->getStockClosedPosition()?->getId();

		$form = $this->adminFormFactory->create();

		$form->addText('pricePerPiece', 'Cena za kus')
			->addRule(Form::Float)
			->setRequired();

		$form->addDatePicker('orderDate', 'Datum objednávky')
			->setRequired();

		$form->addCheckbox('samePriceForBroker', 'Měna objednávky je jiná než u brokera');

		$form->addText('totalBrokerPrice', 'Celková cena objednávky u brokera')
			->addCondition(Form::Filled)
			->addRule(Form::Float)
			->setRequired(false);

		$form->addSelect(
			'brokerCurrency',
			'Měna objenávky u brokera',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER);

		$form->onSuccess[] = function (Form $form) use ($id, $stockPositionId, $onSuccess, $stockPosition): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$stockAsset = $stockPosition->getAsset();
			assert($stockAsset instanceof StockAsset);

			if ($id !== null) {
				$this->stockPositionFacade->update(
					$id,
					TypeValidator::validateFloat($values->pricePerPiece),
					TypeValidator::validateImmutableDatetime($values->orderDate),
					$this->getAssetPriceEmbeddable(
						$stockPosition,
						$values,
					),
					TypeValidator::validateBool($values->samePriceForBroker),
				);
			} else {
				$this->stockPositionFacade->create(
					$stockPositionId,
					TypeValidator::validateFloat($values->pricePerPiece),
					TypeValidator::validateImmutableDatetime($values->orderDate),
					$this->getAssetPriceEmbeddable(
						$stockPosition,
						$values,
					),
					TypeValidator::validateBool($values->samePriceForBroker),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->stockClosedPositionRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function getAssetPriceEmbeddable(
		StockPosition $stockPosition,
		ArrayHash $values,
	): AssetPriceEmbeddable
	{
		if ($values->samePriceForBroker === false) {
			return new AssetPriceEmbeddable(
				$stockPosition->getOrderPiecesCount() * TypeValidator::validateFloat($values->pricePerPiece),
				$stockPosition->getAsset()->getCurrency(),
			);
		}

		return new AssetPriceEmbeddable(
			TypeValidator::validateFloat($values->totalBrokerPrice),
			CurrencyEnum::from(TypeValidator::validateString($values->brokerCurrency)),
		);
	}

	private function setDefaults(Form $form, StockClosedPosition $stockPosition): void
	{
		$defaults = [
			'pricePerPiece' => $stockPosition->getPricePerPiece()->getPrice(),
			'orderDate' => $stockPosition->getDate()->format('Y-m-d'),
			'totalBrokerPrice' => $stockPosition->getTotalCloseAmountInBrokerCurrency()->getPrice(),
			'brokerCurrency' => $stockPosition->getTotalCloseAmountInBrokerCurrency()->getCurrency()->value,
			'samePriceForBroker' => $stockPosition->isDifferentBrokerAmount(),
		];

		$form->setDefaults($defaults);
	}

}
