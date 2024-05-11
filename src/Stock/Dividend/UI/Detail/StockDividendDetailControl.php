<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI\Detail;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\UI\StockAssetDividendRecordGridFactory;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
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
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockDividendDetailControlTemplate::class);
		assert($template instanceof StockDividendDetailControlTemplate);

		$stockAsset = $this->stockAssetRepository->getById($this->stockAssetId);
		$now = $this->datetimeFactory->createNow();
		$template->stockAssetDividendDetailLastDays = $this->stockAssetDividendDetailService->getDetailDTOFromDate(
			$stockAsset,
			$now->deductDaysFromDatetime(self::DAYS_SINCE),
		);

		$lastYear = $this->datetimeFactory->createNow()->deductYearsFromDatetime(1)->getYear();

		$template->stockAssetDividendDetailLastYear = $this->stockAssetDividendDetailService->getDetailDTOForYear(
			$stockAsset,
			$lastYear,
		);

		$template->stockAssetDividendDetailThisYear = $this->stockAssetDividendDetailService->getDetailDTOFromDate(
			$stockAsset,
			$now->setDate(
				$now->getYear(),
				1,
				1,
			),
		);

		$template->currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();
		$template->thisYear = $now->getYear();
		$template->lastYear = $lastYear;
		$template->setFile(__DIR__ . '/StockDividendDetailControl.latte');
		$template->render();
	}

	protected function createComponentStockAssetDividendRecordGrid(): Datagrid
	{
		return $this->stockAssetDividendRecordGridFactory->create($this->stockAssetId);
	}

}
