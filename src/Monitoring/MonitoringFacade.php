<?php

declare(strict_types = 1);

namespace App\Monitoring;

use App\System\SystemValueEnum;
use App\System\SystemValueResolveFacade;
use GuzzleHttp\Client;

class MonitoringFacade
{

	private Client $client;

	/**
	 * @param array<string, string> $monitoringUptimeMonitorMapping
	 */
	public function __construct(
		private array $monitoringUptimeMonitorMapping,
		private SystemValueResolveFacade $systemValueResolveFacade,
	)
	{
		$this->client = new Client();
	}

	public function processUptimeMonitors(): void
	{
		$systemValues = $this->systemValueResolveFacade->getAllValuesByEnumType();

		foreach ($this->monitoringUptimeMonitorMapping as $type => $url) {
			$type = MonitoringUptimeMonitorEnum::tryFrom($type);
			if ($type === null) {
				continue;
			}

			if ($type === MonitoringUptimeMonitorEnum::UPDATED_STOCK_DIVIDENDS_COUNT) {
				if (
					$systemValues[SystemValueEnum::DIVIDENDS_STOCK_ASSETS_WEB->value]
					=== $systemValues[SystemValueEnum::DIVIDENDS_UPDATED_COUNT->value]
				) {
					$this->sendPushMonitor($url);
				}
			}

			if ($type === MonitoringUptimeMonitorEnum::UPDATED_STOCK_PRICES_COUNT) {
				if (
					$systemValues[SystemValueEnum::ENABLED_STOCK_ASSETS->value]
					=== $systemValues[SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT->value]
				) {
					$this->sendPushMonitor($url);
				}
			}

			if ($type === MonitoringUptimeMonitorEnum::UPDATED_STOCK_VALUATION_COUNT) {
				if ($systemValues[SystemValueEnum::STOCK_VALUATION_COUNT->value]
					=== $systemValues[SystemValueEnum::STOCK_VALUATION_DOWNLOADED_COUNT->value]
				) {
					$this->sendPushMonitor($url);
				}
			}
		}
	}

	private function sendPushMonitor(string $url): void
	{
		$this->client->request('GET', $url);
	}

}
