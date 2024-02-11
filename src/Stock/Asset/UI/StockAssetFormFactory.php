<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetFacade;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockAssetFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private StockAssetFacade $stockAssetFacade,
		private StockAssetRepository $stockAssetRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('name', 'Název akcie')
			->setRequired();

		$form->addText('ticker', 'Ticker')
			->setRequired();

		$form->addSelect(
			'currency',
			'Měna',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->addSelect('exchange', 'Burza', StockAssetExchange::getOptionsForAdminSelect())
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->addSelect(
			'assetPriceDownloader',
			'Zdroj dat',
			StockAssetPriceDownloaderEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired();

		$form->addText('isin', 'ISIN')->setNullable();

		$form->addSelect(
			'stockAssetDividendSource',
			'Zdroj dat pro dividendy',
			StockAssetDividendSourceEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired(false);

		$form->addText('dividendTax', 'Dividendová sazba daně')
			->addRule(Form::Float)
			->setNullable()
			->setRequired(false);

		$form->addSelect(
			'brokerDividendCurrency',
			'Měna dividendy v brokerovi',
			CurrencyEnum::getOptionsForAdminSelect(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER)
			->setRequired(false);

		$form->onSuccess[] = function (Form $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->stockAssetFacade->update(
					$id,
					$values->name,
					StockAssetPriceDownloaderEnum::from($values->assetPriceDownloader),
					$values->ticker,
					StockAssetExchange::from($values->exchange),
					CurrencyEnum::from($values->currency),
					$values->isin,
					$values->stockAssetDividendSource !== null ? StockAssetDividendSourceEnum::from(
						$values->stockAssetDividendSource,
					) : null,
					$values->dividendTax,
					$values->brokerDividendCurrency !== null ? CurrencyEnum::from(
						$values->brokerDividendCurrency,
					) : null,
				);
			} else {
				$this->stockAssetFacade->create(
					$values->name,
					StockAssetPriceDownloaderEnum::from($values->assetPriceDownloader),
					$values->ticker,
					StockAssetExchange::from($values->exchange),
					CurrencyEnum::from($values->currency),
					$values->isin,
					StockAssetDividendSourceEnum::from($values->stockAssetDividendSource),
					$values->dividendTax,
					CurrencyEnum::from($values->brokerDividendCurrency),
				);
			}

			$onSuccess();
		};

		if ($id !== null) {
			$this->setDefaults($form, $this->stockAssetRepository->getById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(Form $form, StockAsset $stockAsset): void
	{
		$form->setDefaults([
			'name' => $stockAsset->getName(),
			'assetPriceDownloader' => $stockAsset->getAssetPriceDownloader()->value,
			'ticker' => $stockAsset->getTicker(),
			'exchange' => $stockAsset->getExchange()->value,
			'currency' => $stockAsset->getCurrency()->value,
			'isin' => $stockAsset->getIsin(),
			'stockAssetDividendSource' => $stockAsset->getStockAssetDividendSource()?->value,
			'dividendTax' => $stockAsset->getDividendTax(),
			'brokerDividendCurrency' => $stockAsset->getBrokerDividendCurrency()?->value,
		]);
	}

}
