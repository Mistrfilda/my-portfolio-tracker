<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Asset\Price\Exception\PriceDiffException;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Stock\Position\StockPositionFacade;
use App\UI\Filter\CurrencyFilter;
use App\UI\Filter\PercentageFilter;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use App\Utils\Datetime\DatetimeConst;

class DashboardValueBuilder
{

	public function __construct(
		private readonly CurrencyConversionRepository $currencyConversionRepository,
		private readonly StockPositionFacade $stockPositionFacade,
		private readonly SummaryPriceService $summaryPriceService,
	)
	{
	}

	/**
	 * @return array<int, DashboardValue>
	 */
	public function buildValues(): array
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

		$stockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueSummaryPrice(
			CurrencyEnum::CZK,
		);

		$czStockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueInCzechStocks(
			CurrencyEnum::CZK,
		);

		$usdStockPositionsSummaryPrice = $this->stockPositionFacade->getCurrentPortfolioValueInUsdStocks(
			CurrencyEnum::CZK,
		);

		$totalInvestedAmount = $this->stockPositionFacade->getTotalInvestedAmountSummaryPrice(CurrencyEnum::CZK);

		$values = [
			new DashboardValue(
				'EUR - CZK',
				(string) $eurCzk->getCurrentPrice(),
				TailwindColorConstant::BLUE,
				SvgIcon::CZECH_CROWN,
				sprintf('Aktualizováno %s', $eurCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT)),
			),
			new DashboardValue(
				'USD - CZK',
				(string) $usdCzk->getCurrentPrice(),
				TailwindColorConstant::BLUE,
				SvgIcon::CZECH_CROWN,
				sprintf('Aktualizováno %s', $usdCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT)),
			),
			new DashboardValue(
				'EUR - USD',
				(string) $eurUsd->getCurrentPrice(),
				TailwindColorConstant::BLUE,
				SvgIcon::DOLLAR,
				sprintf('Aktualizováno %s', $eurUsd->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT)),
			),
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
				'Aktuálně zainvestováno',
				CurrencyFilter::format(
					$totalInvestedAmount->getRoundedPrice(),
					CurrencyEnum::CZK,
				),
				TailwindColorConstant::CYAN,
				SvgIcon::CZECH_CROWN,
				sprintf('Celkový počet pozic %s', $totalInvestedAmount->getCounter()),
			),
		];

		$values = array_merge($values, $this->getStockPositionDiff(
			$stockPositionsSummaryPrice,
			$totalInvestedAmount,
		));

		return $values;
	}

	/**
	 * @return array<DashboardValue>
	 * @throws PriceDiffException
	 */
	private function getStockPositionDiff(
		SummaryPrice $currentValue,
		SummaryPrice $investedAmount,
	): array
	{
		$diff = $this->summaryPriceService->getSummaryPriceDiff(
			$currentValue,
			$investedAmount,
		);

		return [
			new DashboardValue(
				'Celkový zisk/ztráta v akciích',
				CurrencyFilter::format(
					$diff->getPriceDifference(),
					$diff->getCurrencyEnum(),
				),
				$diff->getTrend()->getTailwindColor(),
				$diff->getTrend()->getSvgIcon(),
			),
			new DashboardValue(
				'Celkový zisk/ztráta v akciích v procentech',
				PercentageFilter::format($diff->getPercentageDifference()),
				$diff->getTrend()->getTailwindColor(),
				$diff->getTrend()->getSvgIcon(),
			),
		];
	}

}
