<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class CryptoClosedPositionFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly CryptoClosedPositionFacade $cryptoPositionFacade,
		private readonly CryptoClosedPositionRepository $cryptoClosedPositionRepository,
		private readonly CryptoPositionRepository $cryptoPositionRepository,
	)
	{
	}

	public function create(UuidInterface $cryptoPositionId, callable $onSuccess): AdminForm
	{
		$cryptoPosition = $this->cryptoPositionRepository->getById($cryptoPositionId);
		$id = $cryptoPosition->getCryptoClosedPosition()?->getId();

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

		$form->onSuccess[] = function (Form $form) use ($id, $cryptoPositionId, $onSuccess, $cryptoPosition): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$cryptoAsset = $cryptoPosition->getAsset();
			assert($cryptoAsset instanceof CryptoAsset);

			if ($id !== null) {
				$this->cryptoPositionFacade->update(
					$id,
					TypeValidator::validateFloat($values->pricePerPiece),
					TypeValidator::validateImmutableDatetime($values->orderDate),
					$this->getAssetPriceEmbeddable(
						$cryptoPosition,
						$values,
					),
					TypeValidator::validateBool($values->samePriceForBroker),
				);
			} else {
				$this->cryptoPositionFacade->create(
					$cryptoPositionId,
					TypeValidator::validateFloat($values->pricePerPiece),
					TypeValidator::validateImmutableDatetime($values->orderDate),
					$this->getAssetPriceEmbeddable(
						$cryptoPosition,
						$values,
					),
					TypeValidator::validateBool($values->samePriceForBroker),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->cryptoClosedPositionRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function getAssetPriceEmbeddable(
		CryptoPosition $cryptoPosition,
		ArrayHash $values,
	): AssetPriceEmbeddable
	{
		if ($values->samePriceForBroker === false) {
			return new AssetPriceEmbeddable(
				$cryptoPosition->getOrderPiecesCount() * TypeValidator::validateFloat($values->pricePerPiece),
				$cryptoPosition->getAsset()->getCurrency(),
			);
		}

		return new AssetPriceEmbeddable(
			TypeValidator::validateFloat($values->totalBrokerPrice),
			CurrencyEnum::from(TypeValidator::validateString($values->brokerCurrency)),
		);
	}

	private function setDefaults(Form $form, CryptoClosedPosition $cryptoPosition): void
	{
		$defaults = [
			'pricePerPiece' => $cryptoPosition->getPricePerPiece()->getPrice(),
			'orderDate' => $cryptoPosition->getDate()->format('Y-m-d'),
			'totalBrokerPrice' => $cryptoPosition->getTotalCloseAmountInBrokerCurrency()->getPrice(),
			'brokerCurrency' => $cryptoPosition->getTotalCloseAmountInBrokerCurrency()->getCurrency()->value,
			'samePriceForBroker' => $cryptoPosition->isDifferentBrokerAmount(),
		];

		$form->setDefaults($defaults);
	}

}
