<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\Control;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Dividend\Record\UI\StockAssetDividendRecordGridFactory;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use App\UI\Control\Datagrid\Datagrid;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;
use function assert;

class StockAssetDetailControl extends BaseControl
{

	public function __construct(
		private UuidInterface $id,
		private StockAssetRepository $stockAssetRepository,
		private StockPositionFacade $stockPositionFacade,
		private StockAssetDividendRecordGridFactory $stockAssetDividendRecordGridFactory,
		private StockAssetDetailPriceChartProvider $stockAssetDetailPriceChartProvider,
		private DatetimeFactory $datetimeFactory,
		private ChartControlFactory $chartControlFactory,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(StockAssetDetailControlTemplate::class);
		assert($template instanceof StockAssetDetailControlTemplate);

		$template->stockAsset = $this->stockAssetRepository->getById($this->id);
		$template->openStockAssetDetailDTO = $this->stockPositionFacade->getStockAssetDetailDTO($this->id);
		$template->closedStockAssetDetailDTO = $this->stockPositionFacade->getStockAssetDetailDTO(
			$this->id,
			StockAssetListDetailControlEnum::CLOSED_POSITIONS,
		);
		$template->now = $this->datetimeFactory->createNow();
		$template->setFile(__DIR__ . '/StockAssetDetailControl.latte');
		$template->render();
	}

	protected function createComponentStockAssetDividendRecordGrid(): Datagrid
	{
		return $this->stockAssetDividendRecordGridFactory->create($this->id);
	}

	protected function createComponentStockAssetPriceChart(): ChartControl
	{
		$chartProvider = clone $this->stockAssetDetailPriceChartProvider;
		$chartProvider->setId($this->id);

		return $this->chartControlFactory->create(
			ChartType::LINE,
			$this->stockAssetDetailPriceChartProvider,
		);
	}

}
