<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI;

use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendForecastFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private DatetimeFactory $datetimeFactory,
		private StockAssetDividendForecastRepository $stockAssetDividendForecastRepository,
		private StockAssetDividendForecastFacade $stockAssetDividendForecastFacade,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$years = [];
		for ($year = StockAssetDividendForecast::START_YEAR; $year <= $this->datetimeFactory->createNow()->getYear() + 1; $year++) {
			$years[$year] = $year;
		}

		$yearSelect = $form->addSelect('year', 'Rok', $years)->setPrompt(AdminForm::SELECT_PLACEHOLDER);

		$form->addSelect(
			'stockAssetDividendTrend',
			'Trend pro výpočet',
			StockAssetDividendTrendEnum::getAdminSelectOptions(),
		)
			->setPrompt(AdminForm::SELECT_PLACEHOLDER);

		if ($id !== null) {
			$stockAssetDividendForecast = $this->stockAssetDividendForecastRepository->getById($id);
			$form->setDefaults([
				'year' => $stockAssetDividendForecast->getForYear(),
				'stockAssetDividendTrend' => $stockAssetDividendForecast->getTrend()->value,
			]);
			$yearSelect->setDisabled();
		}

		$form->addSubmit('submit', 'Uložit');
		$form->onSuccess[] = function (AdminForm $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			if ($id === null) {
				$this->stockAssetDividendForecastFacade->create(
					TypeValidator::validateInt($values->year),
					StockAssetDividendTrendEnum::from(TypeValidator::validateString($values->stockAssetDividendTrend)),
				);
			} else {
				$this->stockAssetDividendForecastFacade->update(
					$id,
					StockAssetDividendTrendEnum::from(TypeValidator::validateString($values->stockAssetDividendTrend)),
				);
			}

			$onSuccess($id);
		};

		return $form;
	}

}
