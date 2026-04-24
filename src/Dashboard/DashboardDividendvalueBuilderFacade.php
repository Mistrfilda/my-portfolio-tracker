<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\DatetimeFormatFilter;
use App\UI\Filter\SummaryPriceFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use App\Utils\Datetime\DatetimeConst;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Application\LinkGenerator;

class DashboardDividendvalueBuilderFacade implements DashboardValueBuilder
{

	public function __construct(
		private StockAssetDividendFacade $stockAssetDividendFacade,
		private LinkGenerator $linkGenerator,
		private DatetimeFactory $datetimeFactory,
		private StockAssetDividendRecordFacade $stockAssetDividendRecordFacade,
		private StockAssetDividendForecastRepository $stockAssetDividendForecastRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{

	}

	/**
	 * @return array<int, DashboardValueGroup>
	 */
	public function buildValues(): array
	{
		return [
			$this->buildDividendValues(),
		];
	}

	public function buildDividendValues(): DashboardValueGroup
	{
		$lastYearRecords = $this->stockAssetDividendFacade->getLastYearDividendRecordsForDashboard();

		$lastYearTable = new DashboardValueTable(
			'Dividendy minulý rok k současnému datu',
			'Přehled dividend vyplacených společnostmi minulý rok v aktuálním období',
			TailwindColorConstant::GRAY,
			[
				'stockAssetName' => 'Společnost',
				'exDate' => 'Ex-date',
				'amount' => 'Částka',
				'amountWithoutTax' => 'Částka po stržení daně',
			],
		);

		foreach ($lastYearRecords as $lastYearRecord) {
			$lastYearTable->addData([
				'rowColor' => $lastYearRecord->getStockAsset()->hasOpenPositions() ? TailwindColorConstant::GREEN : TailwindColorConstant::GRAY,
				'stockAssetName' => $lastYearRecord->getStockAsset()->getName(),
				'exDate' => DatetimeFormatFilter::formatValue(
					$lastYearRecord->getExDate(),
					DatetimeConst::SYSTEM_DATE_FORMAT,
				),
				'amount' => SummaryPriceFilter::format($lastYearRecord->getSummaryPrice(false)),
				'amountWithoutTax' => SummaryPriceFilter::format($lastYearRecord->getSummaryPrice()),
				'link' => $this->linkGenerator->link(
					'Admin:StockAssetDetail:detail',
					['id' => $lastYearRecord->getStockAsset()->getId()->toString()],
				),
			]);
		}

		$lastDividends = new DashboardValueTable(
			'Poslední dividendy',
			'Přehled obdržených + budoucích oznámených dividend',
			TailwindColorConstant::GRAY,
			[
				'stockAssetName' => 'Společnost',
				'exDate' => 'Ex-date',
				'amount' => 'Částka',
				'amountWithoutTax' => 'Částka po stržení daně',
			],
		);

		$now = $this->datetimeFactory->createNow();
		foreach ($this->stockAssetDividendRecordFacade->getLastDividends(8) as $dividendRecord) {
			$rowColor = TailwindColorConstant::GREEN;
			if ($dividendRecord->getStockAssetDividend()->getExDate() > $now) {
				$rowColor = TailwindColorConstant::BLUE;
			}

			$lastDividends->addData([
				'rowColor' => $rowColor,
				'stockAssetName' => $dividendRecord->getStockAssetDividend()->getStockAsset()->getName(),
				'exDate' => DatetimeFormatFilter::formatValue(
					$dividendRecord->getStockAssetDividend()->getExDate(),
					DatetimeConst::SYSTEM_DATE_FORMAT,
				),
				'amount' => SummaryPriceFilter::format($dividendRecord->getSummaryPrice(false)),
				'amountWithoutTax' => SummaryPriceFilter::format($dividendRecord->getSummaryPrice()),
				'link' => $this->linkGenerator->link(
					'Admin:StockAssetDetail:detail',
					['id' => $dividendRecord->getStockAssetDividend()->getStockAsset()->getId()->toString()],
				),
			]);
		}

		$positions = [];
		$remainingForecastValue = $this->buildRemainingForecastValue($now->getYear());
		if ($remainingForecastValue !== null) {
			$positions[] = $remainingForecastValue;
		}

		foreach ($this->stockAssetDividendRecordFacade->getDividendsByYears() as $yearSummary) {
			$positions[] = new DashboardValue(
				sprintf('Obdržená dividenda celkem za rok %s', $yearSummary->getYear()),
				CurrencyFilter::format(
					$yearSummary->getSummaryPriceWithoutTax()->getPrice(),
					$yearSummary->getSummaryPriceWithoutTax()->getCurrency(),
					0,
				),
				TailwindColorConstant::EMERALD,
				SvgIcon::ARROW_TRENDING_UP,
				sprintf(
					'Dividenda bez daně celkem %s',
					CurrencyFilter::format(
						$yearSummary->getSummaryPriceWithTax()->getPrice(),
						$yearSummary->getSummaryPriceWithoutTax()->getCurrency(),
						0,
					),
				),
			);
		}

		return new DashboardValueGroup(
			DashboardValueGroupEnum::DIVIDENDS,
			'Dividendy',
			'Dividendový přehled',
			$positions,
			true,
			[$lastDividends, $lastYearTable],
		);
	}

	private function buildRemainingForecastValue(int $year): DashboardValue|null
	{
		$defaultForecast = $this->stockAssetDividendForecastRepository->findByDefaultForYear($year);
		if ($defaultForecast === null) {
			return null;
		}

		$remainingDividend = new SummaryPrice(CurrencyEnum::CZK);
		$remainingDividendBeforeTax = new SummaryPrice(CurrencyEnum::CZK);

		foreach ($defaultForecast->getRecords() as $record) {
			$remainingTotal = $record->getRemainingDividendTotal();
			if ($remainingTotal > 0) {
				$remainingDividend->addSummaryPrice(
					$this->convertSummaryPriceToCzk($record->getCurrency(), $remainingTotal),
				);
			}

			$remainingTotalBeforeTax = $record->getRemainingDividendTotalBeforeTax();
			if ($remainingTotalBeforeTax > 0) {
				$remainingDividendBeforeTax->addSummaryPrice(
					$this->convertSummaryPriceToCzk($record->getCurrency(), $remainingTotalBeforeTax),
				);
			}
		}

		return new DashboardValue(
			'Očekávaná dividenda do konce roku',
			CurrencyFilter::format($remainingDividend->getRoundedPrice(), CurrencyEnum::CZK),
			TailwindColorConstant::EMERALD,
			SvgIcon::ARROW_TRENDING_UP,
			sprintf(
				'Defaultní forecast %d, před zdaněním %s',
				$year,
				CurrencyFilter::format($remainingDividendBeforeTax->getRoundedPrice(), CurrencyEnum::CZK),
			),
		);
	}

	private function convertSummaryPriceToCzk(CurrencyEnum $currency, float $price): SummaryPrice
	{
		if ($currency === CurrencyEnum::CZK) {
			return new SummaryPrice(CurrencyEnum::CZK, $price);
		}

		return $this->currencyConversionFacade->getConvertedSummaryPrice(
			new SummaryPrice($currency, $price),
			CurrencyEnum::CZK,
		);
	}

}
