<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI;

use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\UI\Control\StockAssetDividendForecastDetailControl;
use App\Stock\Dividend\Forecast\UI\Control\StockAssetDividendForecastDetailControlFactory;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;
use Ramsey\Uuid\UuidInterface;

/**
 * @property-read StockAssetDividendForecastTemplate $template
 */
class StockAssetDividendForecastPresenter extends BaseAdminPresenter
{

	public function __construct(
		private StockAssetDividendForecastDetailControlFactory $stockAssetDividendForecastDetailControlFactory,
		private StockAssetDividendForecastRepository $stockAssetDividendForecastRepository,
		private StockAssetDividendForecastFormFactory $stockAssetDividendForecastFormFactory,
		private StockAssetDividendForecastFacade $stockAssetDividendForecastFacade,
		private JobRequestFacade $jobRequestFacade,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Predikce dividend';

		$forecastsByYear = [];
		foreach ($this->stockAssetDividendForecastRepository->findAll() as $stockAssetDividendForecast) {
			$forecastsByYear[$stockAssetDividendForecast->getForYear()][] = $stockAssetDividendForecast;
		}

		$this->template->stockAssetDividendForecastsByYear = $forecastsByYear;
	}

	public function renderEdit(string|null $id): void
	{
		if ($id !== null) {
			$stockAssetForecast = $this->stockAssetDividendForecastRepository->getById(
				$this->processParameterRequiredUuid(),
			);
			$this->template->heading = sprintf('Úprava predikce pro rok %s', $stockAssetForecast->getForYear());
		} else {
			$this->template->heading = 'Přidání nové predikce';
		}
	}

	public function renderDetail(string|null $id): void
	{
		$stockAssetForecast = $this->stockAssetDividendForecastRepository->getById(
			$this->processParameterRequiredUuid(),
		);

		$this->template->heading = sprintf('Detail predikce pro rok %s', $stockAssetForecast->getForYear());
	}

	public function handleSetDefaultForYear(string $id): void
	{
		$this->stockAssetDividendForecastFacade->setDefaultForYear($this->processParameterRequiredUuid());
		$this->flashMessage('Defaultní predikce úspěšně nastavena', FlashMessageType::SUCCESS);
		$this->redirect('default');
	}

	public function handleRecalculateAll(): void
	{
		$this->jobRequestFacade->addToQueue(JobRequestTypeEnum::STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE_ALL);
		$this->flashMessage('Přepočtení bylo zařazeno do fronty', FlashMessageType::SUCCESS);
	}

	protected function createComponentStockAssetDividendForecastForm(): AdminForm
	{
		$id = $this->processParameterUuid();
		$onSuccess = function (UuidInterface|null $id): void {
			if ($id === null) {
				$this->flashMessage('Predikce úspěšně vytvořena', FlashMessageType::SUCCESS);

			} else {
				$this->flashMessage('Predikce úspěšně upravena', FlashMessageType::SUCCESS);

			}

			$this->redirect('default');
		};

		return $this->stockAssetDividendForecastFormFactory->create($id, $onSuccess);
	}

	protected function createComponentStockAssetDividendForecastDetailControl(): StockAssetDividendForecastDetailControl
	{
		return $this->stockAssetDividendForecastDetailControlFactory->create($this->processParameterRequiredUuid());
	}

}
