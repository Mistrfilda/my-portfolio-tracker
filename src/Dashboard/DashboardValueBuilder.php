<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Portu\Position\PortuPositionFacade;
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
		private readonly PortuPositionFacade $portuPositionFacade,
		private readonly AssetPriceSummaryFacade $assetPriceSummaryFacade,
	)
	{
	}

	/**
	 * @return array<int, DashboardValueGroup>
	 */
	public function buildValues(): array
	{
		return [
			$this->getCurrencyConversionValues(),
			$this->getTotalValues(),
			$this->getStockValues(),
			$this->getPortuValues(),
		];
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

		return new DashboardValueGroup(
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
				),
				new DashboardValue(
					'Celkový zisk/ztráta ve všech assetech',
					CurrencyFilter::format(
						$diff->getPriceDifference(),
						$diff->getCurrencyEnum(),
					),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
				),
				new DashboardValue(
					'Celkový zisk/ztráta ve všech assetech',
					PercentageFilter::format($diff->getPercentageDifference()),
					$diff->getTrend()->getTailwindColor(),
					$diff->getTrend()->getSvgIcon(),
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

		$totalInvestedAmount = $this->stockPositionFacade->getTotalInvestedAmountSummaryPrice(CurrencyEnum::CZK);

		$diff = $this->summaryPriceService->getSummaryPriceDiff(
			$stockPositionsSummaryPrice,
			$totalInvestedAmount,
		);

		return new DashboardValueGroup(
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
			'Portu',
			positions:
			[
				new DashboardValue(
					'Aktuální zainvestováno v portu',
					CurrencyFilter::format(
						$totalInvestedAmount->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::INDIGO,
					SvgIcon::PORTU,
					sprintf('Celkový počet pozic %s', $totalInvestedAmount->getCounter()),
				),
				new DashboardValue(
					'Aktuální hodnota portfolia v portu',
					CurrencyFilter::format(
						$currentValue->getRoundedPrice(),
						CurrencyEnum::CZK,
					),
					TailwindColorConstant::INDIGO,
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
