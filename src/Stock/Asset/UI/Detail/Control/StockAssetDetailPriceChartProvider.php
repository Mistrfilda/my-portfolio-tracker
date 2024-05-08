<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\Control;

use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Http\Request;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StockAssetDetailPriceChartProvider implements ChartDataProvider
{

	private UuidInterface|null $stockAssetId = null;

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAssetPriceRecordRepository $stockAssetPriceRecordRepository,
		private DatetimeFactory $datetimeFactory,
		private Request $request,
	)
	{
	}

	public function setId(UuidInterface $stockAssetId): void
	{
		$this->stockAssetId = $stockAssetId;
	}

	public function getChartData(): ChartDataSet
	{
		if ($this->stockAssetId === null) {
			throw new InvalidArgumentException();
		}

		$stockAsset = $this->stockAssetRepository->getById($this->stockAssetId);
		$priceRecords = $this->stockAssetPriceRecordRepository->findByStockAssetSinceDate(
			$stockAsset,
			$this->datetimeFactory->createNow()->deductMonthsFromDatetime(3),
		);

		$chartData = new ChartData($stockAsset->getName());
		foreach ($priceRecords as $priceRecord) {
				$chartData->add(
					$priceRecord->getDate()->format('Y-m-d'),
					$priceRecord->getAssetPrice()->getPrice(),
				);
		}

		return new ChartDataSet(
			[$chartData],
			$stockAsset->getCurrency()->format(),
		);
	}

	/**
	 * @param array<mixed> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		if ($this->stockAssetId === null) {
			$path = $this->request->getUrl()->getPathInfo();
			$lastSlashPosition = strrpos($path, '/');
			if ($lastSlashPosition !== false) {
				$this->stockAssetId = Uuid::fromString(substr($path, $lastSlashPosition + 1));
			}
		}
	}

	public function getIdForChart(): string
	{
		return md5(self::class);
	}

}
