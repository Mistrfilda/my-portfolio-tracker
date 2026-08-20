<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist\UI;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Watchlist\Exception\StockAssetWatchlistTickerAlreadyExistsException;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Asset\Watchlist\StockAssetWatchlistFacade;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockAssetWatchlistFormFactory
{

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockAssetWatchlistFacade $stockAssetWatchlistFacade,
		private readonly StockAssetWatchlistRepository $stockAssetWatchlistRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->addText('name', 'Název akcie')
			->setRequired('Zadejte název akcie.');

		$form->addText('ticker', 'Ticker')
			->setRequired('Zadejte ticker.');

		$recommendedEntryPrice = $form->addFloat('recommendedEntryPrice', 'Doporučená vstupní cena')
			->setNullable()
			->setRequired(false)
			->addRule(Form::Min, 'Vstupní cena musí být vyšší než nula.', 0.01);

		$currency = $form->addSelect('currency', 'Měna', CurrencyEnum::getOptionsForAdminSelect())
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired(false);
		$currency->addConditionOn($recommendedEntryPrice, Form::Filled)
			->setRequired('Vyberte měnu vstupní ceny.');

		$form->addSubmit('submit', 'Uložit');

		if ($id !== null) {
			$this->setDefaults($form, $this->stockAssetWatchlistRepository->getById($id));
		}

		$form->onSuccess[] = function (AdminForm $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			try {
				$currency = $values->currency !== null
					? CurrencyEnum::from(TypeValidator::validateString($values->currency))
					: null;
				if ($id === null) {
					$this->stockAssetWatchlistFacade->create(
						TypeValidator::validateString($values->name),
						TypeValidator::validateString($values->ticker),
						TypeValidator::validateNullableFloat($values->recommendedEntryPrice),
						$currency,
					);
				} else {
					$this->stockAssetWatchlistFacade->update(
						$id,
						TypeValidator::validateString($values->name),
						TypeValidator::validateString($values->ticker),
						TypeValidator::validateNullableFloat($values->recommendedEntryPrice),
						$currency,
					);
				}
			} catch (StockAssetWatchlistTickerAlreadyExistsException) {
				$form['ticker']->addError('Tento ticker už na jednoduchém watchlistu existuje.');
				return;
			}

			$onSuccess();
		};

		return $form;
	}

	private function setDefaults(AdminForm $form, StockAssetWatchlist $stockAssetWatchlist): void
	{
		$form->setDefaults([
			'name' => $stockAssetWatchlist->getName(),
			'ticker' => $stockAssetWatchlist->getTicker(),
			'recommendedEntryPrice' => $stockAssetWatchlist->getRecommendedEntryPrice(),
			'currency' => $stockAssetWatchlist->getCurrency()?->value,
		]);
	}

}
