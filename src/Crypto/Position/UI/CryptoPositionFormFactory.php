<?php

declare(strict_types = 1);

namespace App\Crypto\Position\UI;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Position\CryptoPositionFacade;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class CryptoPositionFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly CryptoPositionFacade $cryptoPositionFacade,
		private readonly CryptoAssetRepository $cryptoAssetRepository,
		private readonly CryptoPositionRepository $cryptoPositionRepository,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addSelect('cryptoAsset', 'Kryptoměna', $this->cryptoAssetRepository->findPairs())
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired()
			->setOption('tomSelect', true);

		$form->addText('paidAmount', 'Zaplacená částka')
			->addRule(Form::Float)
			->setRequired();

		$form->addSelect(
			'paidCurrency',
			'Měna platby',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->addText('receivedAmount', 'Získané množství kryptoměny')
			->addRule(Form::Float)
			->setRequired();

		$form->addDatePicker('orderDate', 'Datum objednávky')
			->setRequired();

		$form->onSuccess[] = function (Form $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			$cryptoAsset = $this->cryptoAssetRepository->getById(
				Uuid::fromString(TypeValidator::validateString($values->cryptoAsset)),
			);

			$paidAmount = TypeValidator::validateFloat($values->paidAmount);
			$paidCurrency = CurrencyEnum::from(TypeValidator::validateString($values->paidCurrency));
			$receivedAmount = TypeValidator::validateFloat($values->receivedAmount);
			$orderDate = TypeValidator::validateImmutableDatetime($values->orderDate);

			$paidAmountInAssetCurrency = $this->currencyConversionFacade->convertSimpleValue(
				$paidAmount,
				$paidCurrency,
				$cryptoAsset->getCurrency(),
				$orderDate,
			);

			$pricePerPiece = $paidAmountInAssetCurrency / $receivedAmount;

			$totalInvestedAmountInBrokerCurrency = new AssetPriceEmbeddable(
				$paidAmount,
				$paidCurrency,
			);

			if ($id !== null) {
				$this->cryptoPositionFacade->update(
					$id,
					$cryptoAsset->getId(),
					$receivedAmount,
					$pricePerPiece,
					$orderDate,
					$totalInvestedAmountInBrokerCurrency,
					$paidCurrency !== $cryptoAsset->getCurrency(),
				);
			} else {
				$this->cryptoPositionFacade->create(
					$cryptoAsset->getId(),
					$receivedAmount,
					$pricePerPiece,
					$orderDate,
					$totalInvestedAmountInBrokerCurrency,
					$paidCurrency !== $cryptoAsset->getCurrency(),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->cryptoPositionRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(Form $form, CryptoPosition $cryptoPosition): void
	{
		$paidAmount = $cryptoPosition->getTotalInvestedAmountInBrokerCurrency()->getPrice();
		$paidCurrency = $cryptoPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency();
		$receivedAmount = $cryptoPosition->getOrderPiecesCount();

		$defaults = [
			'cryptoAsset' => $cryptoPosition->getAsset()->getId(),
			'paidAmount' => $paidAmount,
			'paidCurrency' => $paidCurrency->value,
			'receivedAmount' => $receivedAmount,
			'orderDate' => $cryptoPosition->getOrderDate()->format('Y-m-d'),
		];

		$form->setDefaults($defaults);
	}

}
