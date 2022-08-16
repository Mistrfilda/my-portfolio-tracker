<?php

declare(strict_types = 1);

namespace App\Stock\Position\UI;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Position\StockPositionRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StockPositionFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockPositionFacade $stockPositionFacade,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockPositionRepository $stockPositionRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addSelect('stockAsset', 'Akcie', $this->stockAssetRepository->findPairs())
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->addText('orderPiecesCount', 'Počet kusů')
			->addRule(Form::INTEGER)
			->setRequired();

		$form->addText('pricePerPiece', 'Cena za kus')
			->addRule(Form::FLOAT)
			->setRequired();

		$form->addDatePicker('orderDate', 'Datum objednávky')
			->setRequired();

		$form->addCheckbox('samePriceForBroker', 'Měna objednávky je jiná než u brokera');

		$form->addText('totalBrokerPrice', 'Celková cena objednávky u brokera')
			->addCondition(Form::FILLED)
			->addRule(Form::FLOAT)
			->setRequired(false);

		$form->addSelect(
			'brokerCurrency',
			'Měna objenávky u brokera',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER);

		$form->onSuccess[] = function (Form $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$stockAsset = $this->stockAssetRepository->getById(
				Uuid::fromString($values->stockAsset),
			);

			if ($id !== null) {
				$this->stockPositionFacade->update(
					$id,
					$stockAsset->getId(),
					(int) $values->orderPiecesCount,
					(float) $values->pricePerPiece,
					$values->orderDate,
					$this->getAssetPriceEmbeddable(
						$stockAsset,
						$values,
					),
					$values->samePriceForBroker,
				);
			} else {
				$this->stockPositionFacade->create(
					$stockAsset->getId(),
					(int) $values->orderPiecesCount,
					(float) $values->pricePerPiece,
					$values->orderDate,
					$this->getAssetPriceEmbeddable(
						$stockAsset,
						$values,
					),
					$values->samePriceForBroker,
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->stockPositionRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function getAssetPriceEmbeddable(StockAsset $stockAsset, ArrayHash $values): AssetPriceEmbeddable
	{
		if ($values->samePriceForBroker === false) {
			return new AssetPriceEmbeddable(
				(int) $values->orderPiecesCount * (float) $values->pricePerPiece,
				$stockAsset->getCurrency(),
			);
		}

		return new AssetPriceEmbeddable(
			$values->totalBrokerPrice,
			CurrencyEnum::from($values->brokerCurrency),
		);
	}

	private function setDefaults(Form $form, StockPosition $stockPosition): void
	{
		$defaults = [
			'stockAsset' => $stockPosition->getAsset()->getId(),
			'orderPiecesCount' => $stockPosition->getOrderPiecesCount(),
			'pricePerPiece' => $stockPosition->getPricePerPiece()->getPrice(),
			'orderDate' => $stockPosition->getOrderDate()->format('Y-m-d'),
			'totalBrokerPrice' => $stockPosition->getTotalInvestedAmountInBrokerCurrency()->getPrice(),
			'brokerCurrency' => $stockPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency()->value,
			'samePriceForBroker' => $stockPosition->isDifferentBrokerAmount(),
		];

		$form->setDefaults($defaults);
	}

}
