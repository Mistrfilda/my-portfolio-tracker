<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\Control;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Dividend\UI\Detail\StockDividendDetailControl;
use App\Stock\Dividend\UI\Detail\StockDividendDetailControlFactory;
use App\Stock\Position\StockPositionFacade;
use App\UI\Base\BaseControl;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;
use function assert;

class StockAssetDetailControl extends BaseControl
{

	public function __construct(
		private UuidInterface $id,
		private StockAssetRepository $stockAssetRepository,
		private StockPositionFacade $stockPositionFacade,
		private StockAssetDetailPriceChartProvider $stockAssetDetailPriceChartProvider,
		private DatetimeFactory $datetimeFactory,
		private ChartControlFactory $chartControlFactory,
		private StockDividendDetailControlFactory $stockDividendDetailControlFactory,
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

	protected function createComponentStockAssetPriceChart(): ChartControl
	{
		$chartProvider = clone $this->stockAssetDetailPriceChartProvider;
		$chartProvider->setId($this->id);

		return $this->chartControlFactory->create(
			ChartType::LINE,
			$this->stockAssetDetailPriceChartProvider,
		);
	}

	protected function createComponentStockDividendDetailControl(): StockDividendDetailControl
	{
		return $this->stockDividendDetailControlFactory->create($this->id);
	}

}
