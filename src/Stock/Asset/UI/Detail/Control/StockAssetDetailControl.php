<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\Control;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Dividend\UI\Detail\StockDividendDetailControl;
use App\Stock\Dividend\UI\Detail\StockDividendDetailControlFactory;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Valuation\UI\Control\Detail\StockValuationDetailControl;
use App\Stock\Valuation\UI\Control\Detail\StockValuationDetailControlFactory;
use App\UI\Base\BaseControl;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;
use function assert;

class StockAssetDetailControl extends BaseControl
{

	private const CHART_OPTIONS = [
		365 => '365 dní',
		180 => '180 dní',
		90 => '90 dní',
		60 => '60 dní',
		30 => '30 dní',
		5 => '5 dní',
		1 => '1 den',
	];

	public function __construct(
		private UuidInterface $id,
		private int $currentChartDays,
		private StockAssetRepository $stockAssetRepository,
		private StockPositionFacade $stockPositionFacade,
		private StockAssetDetailPriceChartProvider $stockAssetDetailPriceChartProvider,
		private DatetimeFactory $datetimeFactory,
		private ChartControlFactory $chartControlFactory,
		private StockDividendDetailControlFactory $stockDividendDetailControlFactory,
		private StockValuationDetailControlFactory $stockValuationDetailControlFactory,
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
		$template->chartOptions = self::CHART_OPTIONS;
		$template->currentChartDays = $this->currentChartDays;
		$template->setFile(__DIR__ . '/StockAssetDetailControl.latte');
		$template->render();
	}

	protected function createComponentStockAssetPriceChart(): ChartControl
	{
		$chartProvider = clone $this->stockAssetDetailPriceChartProvider;
		$chartProvider->setId($this->id);
		$chartProvider->setNumberOfDays($this->currentChartDays);

		return $this->chartControlFactory->create(
			ChartType::LINE,
			$this->stockAssetDetailPriceChartProvider,
		);
	}

	protected function createComponentStockDividendDetailControl(): StockDividendDetailControl
	{
		return $this->stockDividendDetailControlFactory->create($this->id);
	}

	protected function createComponentStockValuationDetailControl(): StockValuationDetailControl
	{
		return $this->stockValuationDetailControlFactory->create($this->id);
	}

}
