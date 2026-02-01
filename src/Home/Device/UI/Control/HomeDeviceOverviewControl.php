<?php

declare(strict_types = 1);

namespace App\Home\Device\UI\Control;

use App\Home\Device\HomeDevice;
use App\Home\Device\HomeDeviceRepository;
use App\Home\Device\HomeDeviceType;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\Home\Home;
use App\UI\Base\BaseControl;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use InvalidArgumentException;
use Nette\Application\UI\Multiplier;

class HomeDeviceOverviewControl extends BaseControl
{

	/** @var array<HomeDevice> */
	private array $temperatureDevices = [];

	/** @var array<HomeDevice> */
	private array $sensorDevices = [];

	public function __construct(
		private Home $home,
		private readonly HomeDeviceRepository $homeDeviceRepository,
		private readonly HomeDeviceRecordRepository $homeDeviceRecordRepository,
		private readonly ChartControlFactory $chartControlFactory,
		private readonly HomeDeviceTemperatureChartDataProviderFactory $temperatureChartDataProviderFactory,
	)
	{
		$this->loadDevices();
	}

	public function render(): void
	{
		$template = $this->getTemplate();

		$temperatureData = [];
		foreach ($this->temperatureDevices as $device) {
			$latestRecord = $this->homeDeviceRecordRepository->findLatestForDevice($device, 1);
			$temperatureData[] = [
				'device' => $device,
				'latestRecord' => $latestRecord[0] ?? null,
			];
		}

		$sensorData = [];
		foreach ($this->sensorDevices as $device) {
			$latestRecord = $this->homeDeviceRecordRepository->findLatestForDevice($device, 1);
			$sensorData[] = [
				'device' => $device,
				'latestRecord' => $latestRecord[0] ?? null,
			];
		}

		$template->temperatureData = $temperatureData;
		$template->sensorData = $sensorData;
		$template->setFile(__DIR__ . '/HomeDeviceOverviewControl.latte');
		$template->render();
	}

	/** @return Multiplier<ChartControl> */
	public function createComponentTemperatureChart(): Multiplier
	{
		return new Multiplier(function (string $index): ChartControl {
			$index = (int) $index;
			if (!isset($this->temperatureDevices[$index])) {
				throw new InvalidArgumentException('Device not found at index ' . $index);
			}

			$device = $this->temperatureDevices[$index];
			$provider = $this->temperatureChartDataProviderFactory->create($device);

			return $this->chartControlFactory->create(ChartType::LINE, $provider);
		});
	}

	private function loadDevices(): void
	{
		$qb = $this->homeDeviceRepository->createQueryBuilderForHome($this->home);
		/** @var array<HomeDevice> $devices */
		$devices = $qb->getQuery()->getResult();

		foreach ($devices as $device) {
			if ($device->getType() === HomeDeviceType::TEMPERATURE) {
				$this->temperatureDevices[] = $device;
			} elseif ($device->getType() === HomeDeviceType::SENSOR) {
				$this->sensorDevices[] = $device;
			}
		}
	}

}
