<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\UI\Base\BaseControl;
use App\UI\FlashMessage\FlashMessageType;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @property-read StockAssetDividendForecastDetailControlTemplate $template
 */
class StockAssetDividendForecastDetailControl extends BaseControl
{

	private StockAssetDividendForecast $stockAssetDividendForecast;

	public function __construct(
		UuidInterface $forecastId,
		private StockAssetDividendForecastRepository $stockAssetDividendForecastRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
		private JobRequestFacade $jobRequestFacade,
		private StockAssetDividendForecastItemValuesFormFactory $stockAssetDividendForecastItemValuesFormFactory,
	)
	{
		$this->stockAssetDividendForecast = $this->stockAssetDividendForecastRepository->getById($forecastId);
	}

	public function handleRecalculate(string $id): void
	{
		$this->jobRequestFacade->addToQueue(
			JobRequestTypeEnum::STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE,
			['id' => $id],
		);
		$this->presenter->flashMessage('Přepočtení bylo zařazeno do fronty', FlashMessageType::SUCCESS);
		$this->presenter->redirect('this');
	}

	public function render(): void
	{
		$this->template->forecast = $this->stockAssetDividendForecast;
		$this->template->records = $this->stockAssetDividendForecast->getRecords();

		$totalsByCurrency = [];
		$forYear = $this->stockAssetDividendForecast->getForYear();
		$czkTotalAlreadyReceived = new SummaryPrice(CurrencyEnum::CZK);
		$czkTotalRemaining = new SummaryPrice(CurrencyEnum::CZK);
		$czkTotalYear = new SummaryPrice(CurrencyEnum::CZK);

		foreach ($this->stockAssetDividendForecast->getRecords() as $record) {
			$currency = $record->getCurrency()->value;

			if (!isset($totalsByCurrency[$currency])) {
				$totalsByCurrency[$currency] = [
					'currency' => $record->getCurrency(),
					'alreadyReceived' => 0.0,
					'totalYear' => 0.0,
					'remaining' => 0.0,
				];
			}

			// Get actual received from dividend records for this stock asset
			$actualReceivedForStock = 0.0;
			foreach ($record->getStockAsset()->getDividends() as $dividend) {
				if ($dividend->getExDate()->getYear() !== $forYear) {
					continue;
				}

				foreach ($dividend->getRecords() as $dividendRecord) {
					$actualReceivedForStock += $dividendRecord->getSummaryPrice()->getPrice();

					// Add to CZK total - use ex-date for conversion
					$amountToConvert = $dividendRecord->getSummaryPrice();
					$currencyToConvert = $dividendRecord->getCurrency();

					if ($currencyToConvert !== CurrencyEnum::CZK) {
						$converted = $this->currencyConversionFacade->getConvertedSummaryPrice(
							$amountToConvert,
							CurrencyEnum::CZK,
							$dividend->getExDate(),
						);
						$czkTotalAlreadyReceived->addSummaryPrice($converted);
					} else {
						$czkTotalAlreadyReceived->addFlat($amountToConvert->getPrice(), 0);
					}
				}
			}

			$remainingForStock = $record->getRemainingDividendTotal();
			$totalYearForStock = $actualReceivedForStock + $remainingForStock;

			$totalsByCurrency[$currency]['alreadyReceived'] += $actualReceivedForStock;
			$totalsByCurrency[$currency]['totalYear'] += $totalYearForStock;
			$totalsByCurrency[$currency]['remaining'] += $remainingForStock;

			if ($remainingForStock > 0) {
				if ($record->getCurrency() !== CurrencyEnum::CZK) {
					$convertedRemaining = $this->currencyConversionFacade->getConvertedSummaryPrice(
						new SummaryPrice($record->getCurrency(), $remainingForStock),
						CurrencyEnum::CZK,
					);
					$czkTotalRemaining->addSummaryPrice($convertedRemaining);
				} else {
					$czkTotalRemaining->addFlat($remainingForStock, 0);
				}
			}
		}

		$czkTotalYear->addSummaryPrice($czkTotalAlreadyReceived);
		$czkTotalYear->addSummaryPrice($czkTotalRemaining);

		$this->template->totalsByCurrency = $totalsByCurrency;
		$this->template->czkTotalAlreadyReceived = $czkTotalAlreadyReceived;
		$this->template->czkTotalRemaining = $czkTotalRemaining;
		$this->template->czkTotalYear = $czkTotalYear;

		$this->template->setFile(__DIR__ . '/StockAssetDividendForecastDetailControl.latte');
		$this->template->render();
	}

	/**
	 * @return Multiplier<Form>
	 */
	protected function createComponentStockAssetDividendForecastItemValuesForm(): Multiplier
	{
		$onSuccess = function (): void {
			$this->getPresenter()->flashMessage('Uloženo', FlashMessageType::SUCCESS);
			$this->getPresenter()->redirect('this');
		};

		return new Multiplier(function (string $id) use ($onSuccess): Form {
			$id = Uuid::fromString(str_replace('_', '-', $id));
			return $this->stockAssetDividendForecastItemValuesFormFactory->create($id, $onSuccess);
		});
	}

}
