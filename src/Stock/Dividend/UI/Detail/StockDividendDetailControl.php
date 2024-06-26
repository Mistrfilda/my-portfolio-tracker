<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\UI\StockAssetDividendRecordGridFactory;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use App\UI\Control\Datagrid\Datagrid;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class StockDividendDetailControl extends BaseControl
{

	public const DAYS_SINCE = 365;

	public function __construct(
		private UuidInterface $stockAssetId,
		private StockAssetRepository $stockAssetRepository,
		private StockAssetDividendRecordGridFactory $stockAssetDividendRecordGridFactory,
		private StockAssetDividendDetailService $stockAssetDividendDetailService,
		private DatetimeFactory $datetimeFactory,
		private StockPositionFacade $stockPositionFacade,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockDividendDetailControlTemplate::class);
		assert($template instanceof StockDividendDetailControlTemplate);

		$stockAsset = $this->stockAssetRepository->getById($this->stockAssetId);
		$now = $this->datetimeFactory->createNow();
		$stockAssetDividendDetailLastDays = $this->stockAssetDividendDetailService->getDetailDTOFromDate(
			$stockAsset,
			$now->deductDaysFromDatetime(self::DAYS_SINCE),
		);

		$lastYear = $this->datetimeFactory->createNow()->deductYearsFromDatetime(1)->getYear();

		$stockAssetDividendDetailLastYear = $this->stockAssetDividendDetailService->getDetailDTOForYear(
			$stockAsset,
			$lastYear,
		);

		$stockAssetDividendDetailThisYear = $this->stockAssetDividendDetailService->getDetailDTOFromDate(
			$stockAsset,
			$now->setDate(
				$now->getYear(),
				1,
				1,
			),
		);

		$template->dividendDetailDTOs = [
			[
				'label' => sprintf(
					'Dividendy za posledních 365 dnů (počet %s)',
					$stockAssetDividendDetailLastDays->getDividendsSummaryPriceWithTax()->getCounter(),
				),
				'detailDTO' => $stockAssetDividendDetailLastDays,
			],
			[
				'label' => sprintf(
					'Dividendy tento rok %s (počet %s)',
					$now->getYear(),
					$stockAssetDividendDetailThisYear->getDividendsSummaryPriceWithTax()->getCounter(),
				),
				'detailDTO' => $stockAssetDividendDetailThisYear,
			],
			[
				'label' => sprintf(
					'Dividendy za poslední rok %s (počet %s)',
					$lastYear,
					$stockAssetDividendDetailLastYear->getDividendsSummaryPriceWithTax()->getCounter(),
				),
				'detailDTO' => $stockAssetDividendDetailLastYear,
			],
		];

		$template->openStockAssetDetailDTO = $this->stockPositionFacade->getStockAssetDetailDTO($this->stockAssetId);
		$template->currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();
		$template->lastYear = $lastYear;
		$template->setFile(__DIR__ . '/StockDividendDetailControl.latte');
		$template->render();
	}

	protected function createComponentStockAssetDividendRecordGrid(): Datagrid
	{
		return $this->stockAssetDividendRecordGridFactory->create($this->stockAssetId);
	}

}
