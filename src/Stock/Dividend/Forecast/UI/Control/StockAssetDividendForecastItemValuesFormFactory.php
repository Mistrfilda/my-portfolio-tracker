<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI\Control;

use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordRepository;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendForecastItemValuesFormFactory
{

	public function __construct(
		private AdminFormFactory $formFactory,
		private StockAssetDividendForecastRecordFacade $stockAssetDividendForecastFacade,
		private StockAssetDividendForecastRecordRepository $stockAssetDividendForecastRecordRepository,
	)
	{
	}

	public function create(UuidInterface $id, callable $onSuccess): Form
	{
		$record = $this->stockAssetDividendForecastRecordRepository->getById($id);
		$form = $this->formFactory->create();
		$form->addFloat(
			'customDividendUsedForCalculation',
			'Specifikovat vlastní dividendu (čistá)',
		)->setValue($record->getCustomDividendUsedForCalculation())->setNullable();
		$form->addFloat(
			'customGrossDividendUsedForCalculation',
			'Specifikovat vlastní dividendu (hrubá)',
		)->setValue($record->getCustomGrossDividendUsedForCalculation())->setNullable();
		$form->addFloat(
			'expectedSpecialDividendThisYearPerStock',
			'Specifikovat speciální dividendu pro tento rok (čistá)',
		)->setValue($record->getExpectedSpecialDividendThisYearPerStock())->setNullable();
		$form->addFloat(
			'expectedSpecialDividendThisYearPerStockBeforeTax',
			'Specifikovat speciální dividendu pro tento rok (hrubá)',
		)->setValue($record->getExpectedSpecialDividendThisYearPerStockBeforeTax())->setNullable();

		$form->onSuccess[] = function (Form $form) use ($record, $onSuccess): void {
			$values = $form->getValues();
			assert($values instanceof ArrayHash);
			$this->stockAssetDividendForecastFacade->updateCustomValuesForRecord(
				$record->getId(),
				TypeValidator::validateNullableFloat($values->customDividendUsedForCalculation),
				TypeValidator::validateNullableFloat($values->customGrossDividendUsedForCalculation),
				TypeValidator::validateNullableFloat($values->expectedSpecialDividendThisYearPerStock),
				TypeValidator::validateNullableFloat($values->expectedSpecialDividendThisYearPerStockBeforeTax),
			);

			$onSuccess($record->getStockAssetDividendForecast()->getId()->toString());
		};

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

}
