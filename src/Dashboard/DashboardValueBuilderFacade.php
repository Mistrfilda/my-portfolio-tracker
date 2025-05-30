<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Portu\Position\PortuPositionFacade;
use App\Statistic\PortolioStatisticType;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockPositionFacade;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use App\Utils\Datetime\DatetimeConst;

class DashboardValueBuilderFacade implements DashboardValueBuilder
{

	public function __construct(
		private readonly CurrencyConversionRepository $currencyConversionRepository,
		private readonly StockPositionFacade $stockPositionFacade,
		private readonly SummaryPriceService $summaryPriceService,
		private readonly PortuPositionFacade $portuPositionFacade,
		private readonly AssetPriceSummaryFacade $assetPriceSummaryFacade,
		private readonly DashboardDividendvalueBuilderFacade $dashboardDividendvalueBuilderFacade,
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
			$this->dashboardDividendvalueBuilderFacade->buildDividendValues(),
			$this->getCurrencyConversionValues(),
		];
	}

	private function getCurrencyConversionValues(): DashboardValueGroup
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

		$nokCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::NOK,
			CurrencyEnum::CZK,
		);

		$nokEur = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::NOK,
			CurrencyEnum::EUR,
		);

		$eurNok = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::NOK,
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
				new DashboardValue(
					'NOK - CZK',
					(string) $nokCzk->getCurrentPrice(),
					TailwindColorConstant::ROSE,
					SvgIcon::NORWEGIAN_KRONE,
					sprintf(
						'Aktualizováno %s',
						$gbpCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'NOK - EUR',
					(string) $nokEur->getCurrentPrice(),
					TailwindColorConstant::ROSE,
					SvgIcon::NORWEGIAN_KRONE,
					sprintf(
						'Aktualizováno %s',
						$gbpEur->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
				new DashboardValue(
					'EUR - NOK',
					(string) $eurNok->getCurrentPrice(),
					TailwindColorConstant::ROSE,
					SvgIcon::NORWEGIAN_KRONE,
					sprintf(
						'Aktualizováno %s',
						$gbpEur->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT),
					),
				),
			],
		);
	}

	private function getTotalValues(): DashboardValueGroup
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

	private function getStockValues(): DashboardValueGroup
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
