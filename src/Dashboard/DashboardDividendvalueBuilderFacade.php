<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendRemainingForecastProvider;
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
		private StockAssetDividendRemainingForecastProvider $remainingForecastProvider,
		private CurrentAppAdminGetter $currentAppAdminGetter,
		private StockAssetRepository $stockAssetRepository,
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

	public function buildDividendValues(AppAdmin|null $appAdmin = null): DashboardValueGroup
	{
		$appAdmin ??= $this->currentAppAdminGetter->getAppAdmin();
		$ownedAssets = [];
		foreach ($this->stockAssetRepository->findWithPositionsForAppAdmin($appAdmin, true) as $asset) {
			$ownedAssets[$asset->getId()->toString()] = true;
		}

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
				'rowColor' => isset($ownedAssets[$lastYearRecord->getStockAsset()->getId()->toString()])
					? TailwindColorConstant::GREEN
					: TailwindColorConstant::GRAY,
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
		foreach ($this->stockAssetDividendRecordFacade->getLastDividends(8, $appAdmin) as $dividendRecord) {
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
		$remainingForecastValue = $this->buildRemainingForecastValue($now->getYear(), $appAdmin);
		if ($remainingForecastValue !== null) {
			$positions[] = $remainingForecastValue;
		}

		foreach ($this->stockAssetDividendRecordFacade->getDividendsByYears($appAdmin) as $yearSummary) {
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

	private function buildRemainingForecastValue(int $year, AppAdmin $appAdmin): DashboardValue|null
	{
		$forecast = $this->remainingForecastProvider->getForYear($year, $appAdmin);
		if ($forecast === null) {
			return null;
		}

		return new DashboardValue(
			'Očekávaná dividenda do konce roku',
			CurrencyFilter::format($forecast->getSummaryPriceWithoutTax()->getRoundedPrice(), CurrencyEnum::CZK),
			TailwindColorConstant::EMERALD,
			SvgIcon::ARROW_TRENDING_UP,
			sprintf(
				'Defaultní forecast %d, před zdaněním %s',
				$year,
				CurrencyFilter::format($forecast->getSummaryPriceWithTax()->getRoundedPrice(), CurrencyEnum::CZK),
			),
		);
	}

}
