<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Portu\Position\PortuPositionFacade;
use App\Statistic\PortolioStatisticType;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockPositionFacade;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\DatetimeFormatFilter;
use App\UI\Filter\PercentageFilter;
use App\UI\Filter\SummaryPriceFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use App\Utils\Datetime\DatetimeConst;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Application\LinkGenerator;

class DashboardValueBuilderFacade implements DashboardValueBuilder
{

	public function __construct(
		private readonly CurrencyConversionRepository $currencyConversionRepository,
		private readonly StockPositionFacade $stockPositionFacade,
		private readonly SummaryPriceService $summaryPriceService,
		private readonly PortuPositionFacade $portuPositionFacade,
		private readonly AssetPriceSummaryFacade $assetPriceSummaryFacade,
		private readonly StockAssetDividendFacade $stockAssetDividendFacade,
		private readonly LinkGenerator $linkGenerator,
		private readonly StockAssetDividendRecordFacade $stockAssetDividendRecordFacade,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockClosedPositionFacade $stockClosedPositionFacade,
	)
	{
	}

	/**
	 * @return array<int, DashboardValueGroup>
	 */
	public function buildValues(): array
	{
		return [
			$this->getTotalValues(),
			$this->getStockValues(),
			$this->getPortuValues(),
			$this->buildDividendValues(),
			$this->getCurrencyConversionValues(),
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

	public function getCurrencyConversionValues(): DashboardValueGroup
	{
		$eurCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
		);

		$usdCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
		);

		$eurUsd = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::USD,
		);

		$gbpCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::GBP,
			CurrencyEnum::CZK,
		);

		$gbpEur = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::GBP,
			CurrencyEnum::EUR,
		);

		$eurGbp = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::GBP,
		);

		$plnCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::PLN,
			CurrencyEnum::CZK,
		);

		$plnEur = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::PLN,
			CurrencyEnum::EUR,
		);

		$eurPln = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::PLN,
		);

		return new DashboardValueGroup(
			DashboardValueGroupEnum::CURRENCY,
			'Kurzy měn',
			'Aktuální kurzy měn',
			[
				new DashboardValue(
					'EUR - CZK',
					(string) $eurCzk->getCurrentPrice(),
					TailwindColorConstant::BLUE,
					SvgIcon::CZECH_CROWN,
					sprintf(
						'Aktualizováno %s',
						$eurCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'USD - CZK',
					(string) $usdCzk->getCurrentPrice(),
					TailwindColorConstant::BLUE,
					SvgIcon::CZECH_CROWN,
					sprintf(
						'Aktualizováno %s',
						$usdCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'EUR - USD',
					(string) $eurUsd->getCurrentPrice(),
					TailwindColorConstant::BLUE,
					SvgIcon::DOLLAR,
					sprintf(
						'Aktualizováno %s',
						$eurUsd->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'GBP - CZK',
					(string) $gbpCzk->getCurrentPrice(),
					TailwindColorConstant::CYAN,
					SvgIcon::BRITISH_POUND,
					sprintf(
						'Aktualizováno %s',
						$gbpCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'GBP - EUR',
					(string) $gbpEur->getCurrentPrice(),
					TailwindColorConstant::CYAN,
					SvgIcon::BRITISH_POUND,
					sprintf(
						'Aktualizováno %s',
						$gbpEur->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'EUR - GBP',
					(string) $eurGbp->getCurrentPrice(),
					TailwindColorConstant::CYAN,
					SvgIcon::BRITISH_POUND,
					sprintf(
						'Aktualizováno %s',
						$gbpEur->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'PLN - CZK',
					(string) $plnCzk->getCurrentPrice(),
					TailwindColorConstant::RED,
					SvgIcon::POLISH_ZLOTY,
					sprintf(
						'Aktualizováno %s',
						$gbpCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'PLN - EUR',
					(string) $plnEur->getCurrentPrice(),
					TailwindColorConstant::RED,
					SvgIcon::POLISH_ZLOTY,
					sprintf(
						'Aktualizováno %s',
						$gbpEur->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'EUR - PLN',
					(string) $eurPln->getCurrentPrice(),
					TailwindColorConstant::RED,
					SvgIcon::POLISH_ZLOTY,
					sprintf(
						'Aktualizováno %s',
						$gbpEur->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
			],
		);
	}

	public function getTotalValues(): DashboardValueGroup
	{
		$totalInvestedAmount = $this->assetPriceSummaryFacade->getTotalInvestedAmount(
			CurrencyEnum::CZK,
		);

		$currentValue = $this->assetPriceSummaryFacade->getCurrentValue(
			CurrencyEnum::CZK,
		);

		$diff = $this->summaryPriceService->getSummaryPriceDiff(
			$currentValue,
			$totalInvestedAmount,
		);

		return new DashboardValueGroup(
			DashboardValueGroupEnum::TOTAL_VALUES,
			'Celkové hodnoty portfolia',
			positions: [
				new DashboardValue(
					'Aktuální zainvestováno ve všech assetech',
					CurrencyFilter::format(
						$totalInvestedAmount->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::BLUE,
					SvgIcon::CZECH_CROWN,
					sprintf('Celkový počet pozic %s', $totalInvestedAmount->getCounter()),
					PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
				),
				new DashboardValue(
					'Aktuální hodnota ve všech assetech',
					CurrencyFilter::format(
						$currentValue->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::BLUE,
					SvgIcon::CZECH_CROWN,
					sprintf('Celkový počet pozic %s', $currentValue->getCounter()),
					PortolioStatisticType::TOTAL_VALUE_IN_CZK,
				),
				new DashboardValue(
					'Celkový zisk/ztráta ve všech assetech',
					CurrencyFilter::format(
						$diff->getPriceDifference(),
						$diff->getCurrencyEnum(),
					),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
					type: PortolioStatisticType::TOTAL_PROFIT,
				),
				new DashboardValue(
					'Celkový zisk/ztráta ve všech assetech',
					PercentageFilter::format($diff->getPercentageDifference()),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
					type: PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE,
				),
			],
		);
	}

	public function getStockValues(): DashboardValueGroup
	{
		$stockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueSummaryPrice(
			CurrencyEnum::CZK,
		);

		$czStockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueInCzechStocks(
			CurrencyEnum::CZK,
		);

		$usdStockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueInUsdStocks(
			CurrencyEnum::CZK,
		);

		$gbpStockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueInGbpStocks(
			CurrencyEnum::CZK,
		);

		$eurStockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueInEurStocks(
			CurrencyEnum::CZK,
		);

		$totalInvestedAmount = $this->stockPositionFacade->getTotalInvestedAmountSummaryPrice(CurrencyEnum::CZK);

		$diff = $this->summaryPriceService->getSummaryPriceDiff(
			$stockPositionsSummaryPrice,
			$totalInvestedAmount,
		);

		$closedSummaryPrice = $this->stockClosedPositionFacade->getAllStockClosedPositionsSummaryPrice();

		return new DashboardValueGroup(
			DashboardValueGroupEnum::STOCKS,
			'Akcie',
			positions:
			[
				new DashboardValue(
					'Aktuální hodnota portfolia v akciích',
					CurrencyFilter::format(
						$stockPositionsSummaryPrice->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::EMERALD,
					SvgIcon::COLLECTION,
					sprintf('Celkový počet pozic %s', $stockPositionsSummaryPrice->getCounter()),
				),
				new DashboardValue(
					'Aktuální hodnota portfolia v českých akciích',
					CurrencyFilter::format(
						$czStockPositionsSummaryPrice->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::EMERALD,
					SvgIcon::CZECH_CROWN,
					sprintf('Celkový počet pozic %s', $czStockPositionsSummaryPrice->getCounter()),
				),
				new DashboardValue(
					'Aktuální hodnota portfolia v amerických akciích',
					CurrencyFilter::format(
						$usdStockPositionsSummaryPrice->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::EMERALD,
					SvgIcon::DOLLAR,
					sprintf('Celkový počet pozic %s', $usdStockPositionsSummaryPrice->getCounter()),
				),
				new DashboardValue(
					'Aktuální hodnota portfolia v UK akciích',
					CurrencyFilter::format(
						$gbpStockPositionsSummaryPrice->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::EMERALD,
					SvgIcon::BRITISH_POUND,
					sprintf('Celkový počet pozic %s', $gbpStockPositionsSummaryPrice->getCounter()),
				),
				new DashboardValue(
					'Aktuální hodnota portfolia v EUR akciích',
					CurrencyFilter::format(
						$eurStockPositionsSummaryPrice->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::EMERALD,
					SvgIcon::BRITISH_POUND,
					sprintf('Celkový počet pozic %s', $eurStockPositionsSummaryPrice->getCounter()),
				),
				new DashboardValue(
					'Aktuálně zainvestováno v akciích',
					CurrencyFilter::format(
						$totalInvestedAmount->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::CYAN,
					SvgIcon::CZECH_CROWN,
					sprintf('Celkový počet pozic %s', $totalInvestedAmount->getCounter()),
				),
				new DashboardValue(
					'Celkový zisk/ztráta v otevřených pozicích',
					CurrencyFilter::format(
						$diff->getPriceDifference(),
						$diff->getCurrencyEnum(),
					),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
				),
				new DashboardValue(
					'Celkový zisk/ztráta v otevřených pozicích',
					PercentageFilter::format($diff->getPercentageDifference()),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
				),
				new DashboardValue(
					'Celkový zisk/ztráta v uzavřených pozicích',
					CurrencyFilter::format(
						$closedSummaryPrice->getPriceDifference(),
						$closedSummaryPrice->getCurrencyEnum(),
					),
					$closedSummaryPrice->getTrend()->getTailwindColor(),
					$closedSummaryPrice->getTrend()->getSvgIcon(),
				),
				new DashboardValue(
					'Celkový zisk/ztráta v uzavřených pozicích',
					PercentageFilter::format($closedSummaryPrice->getPercentageDifference()),
					$closedSummaryPrice->getTrend()->getTailwindColor(),
					$closedSummaryPrice->getTrend()->getSvgIcon(),
				),
			],
		);
	}

	private function getPortuValues(): DashboardValueGroup
	{
		$totalInvestedAmount = $this->portuPositionFacade->getTotalInvestedAmountSummaryPrice(
			CurrencyEnum::CZK,
		);

		$currentValue = $this->portuPositionFacade->getCurrentPortfolioValueSummaryPrice(
			CurrencyEnum::CZK,
		);

		$diff = $this->summaryPriceService->getSummaryPriceDiff(
			$currentValue,
			$totalInvestedAmount,
		);

		return new DashboardValueGroup(
			DashboardValueGroupEnum::PORTU,
			'Portu',
			positions:
			[
				new DashboardValue(
					'Aktuální zainvestováno v portu',
					CurrencyFilter::format(
						$totalInvestedAmount->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::BLACK,
					SvgIcon::PORTU,
					sprintf('Celkový počet pozic %s', $totalInvestedAmount->getCounter()),
				),
				new DashboardValue(
					'Aktuální hodnota portfolia v portu',
					CurrencyFilter::format(
						$currentValue->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::BLACK,
					SvgIcon::PORTU,
					sprintf('Celkový počet pozic %s', $currentValue->getCounter()),
				),
				new DashboardValue(
					'Celkový zisk/ztráta v portu',
					CurrencyFilter::format(
						$diff->getPriceDifference(),
						$diff->getCurrencyEnum(),
					),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
				),
				new DashboardValue(
					'Celkový zisk/ztráta v portu v procentech',
					PercentageFilter::format($diff->getPercentageDifference()),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
				),
			],
		);
	}

}
