<?php

declare(strict_types = 1);

namespace App\Home\Device\UI\Control;

use App\Home\Device\HomeDevice;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;
use Mistrfilda\Datetime\DatetimeFactory;

class HomeDeviceTemperatureChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private HomeDevice $homeDevice,
		private readonly HomeDeviceRecordRepository $homeDeviceRecordRepository,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function getChartData(): ChartDataSet
	{
		$chartData = new ChartData($this->homeDevice->getName(), useBackgroundColors: false);

		$since = $this->datetimeFactory->createNow()->deductHoursFromDatetime(24);
		$records = $this->homeDeviceRecordRepository->findForDeviceSince($this->homeDevice, $since);

		foreach ($records as $record) {
			if ($record->getFloatValue() !== null) {
				$chartData->add(
					$record->getCreatedAt()->format('H:i'),
					$record->getFloatValue(),
				);
			}
		}

		$unit = $this->homeDevice->getRecords()->first();
		$suffix = $unit !== false && $unit->getUnit() !== null ? $unit->getUnit()->format() : '°C';

		return new ChartDataSet([$chartData], tooltipSuffix: $suffix);
	}

	/** @param array<string, string> $parameters */
	public function processParametersFromRequest(array $parameters): void
	{
		// do nothing
	}

	public function getIdForChart(): string
	{
		return 'home-device-temperature-' . $this->homeDevice->getId()->toString();
	}

}
