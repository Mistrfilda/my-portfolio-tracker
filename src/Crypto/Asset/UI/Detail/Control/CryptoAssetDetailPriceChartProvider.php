<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\Control;

use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Http\Request;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class CryptoAssetDetailPriceChartProvider implements ChartDataProvider
{

	private UuidInterface|null $cryptoAssetId = null;

	private int|null $numberOfDays = null;

	public function __construct(
		private CryptoAssetRepository $cryptoAssetRepository,
		private CryptoAssetPriceRecordRepository $cryptoAssetPriceRecordRepository,
		private DatetimeFactory $datetimeFactory,
		private Request $request,
	)
	{
	}

	public function setId(UuidInterface $cryptoAssetId): void
	{
		$this->cryptoAssetId = $cryptoAssetId;
	}

	public function setNumberOfDays(int $numberOfDays): void
	{
		$this->numberOfDays = $numberOfDays;
	}

	public function getChartData(): ChartDataSet
	{
		if ($this->cryptoAssetId === null) {
			throw new InvalidArgumentException();
		}

		if ($this->numberOfDays === null) {
			throw new InvalidArgumentException();
		}

		$cryptoAsset = $this->cryptoAssetRepository->getById($this->cryptoAssetId);
		$priceRecords = $this->cryptoAssetPriceRecordRepository->findByCryptoAssetSinceDate(
			$cryptoAsset,
			$this->datetimeFactory->createNow()->deductDaysFromDatetime($this->numberOfDays),
		);

		$chartData = new ChartData($cryptoAsset->getName());
		foreach ($priceRecords as $priceRecord) {
				$chartData->add(
					$priceRecord->getDate()->format('Y-m-d'),
					$priceRecord->getAssetPrice()->getPrice(),
				);
		}

		return new ChartDataSet(
			[$chartData],
			$cryptoAsset->getCurrency()->format(),
		);
	}

	/**
	 * @param array<mixed> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		if ($this->cryptoAssetId === null) {
			$path = $this->request->getUrl()->getPathInfo();
			$lastSlashPosition = strrpos($path, '/');
			if ($lastSlashPosition !== false) {
				$this->cryptoAssetId = Uuid::fromString(substr($path, $lastSlashPosition + 1));
			}
		}

		if ($this->numberOfDays === null) {
			$currentChartDays = $this->request->getQuery('currentChartDays');
			if ($currentChartDays === null) {
				$this->numberOfDays = 90;
			} else {
				assert(is_string($currentChartDays));
				$this->numberOfDays = (int) $currentChartDays;
			}
		}
	}

	public function getIdForChart(): string
	{
		return md5(self::class);
	}

}
