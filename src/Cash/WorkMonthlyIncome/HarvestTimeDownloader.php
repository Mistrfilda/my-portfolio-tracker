<?php

declare(strict_types = 1);

namespace App\Cash\WorkMonthlyIncome;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use Nette\Utils\Json;

/**
 * @phpstan-type HarvestTimeEntry array{
 *     hours: float,
 *     spent_date: string
 * }
 */
class HarvestTimeDownloader
{

	private const FROM_DATE = '2024-01-01';

	private const URL = 'https://api.harvestapp.com/v2/time_entries?user_id=%s&from=%s&page=%s';

	public function __construct(
		private int $harvestAccountId,
		private int $harvestUserId,
		private string $harvestAccessToken,
		private Psr18ClientFactory $psr18ClientFactory,
		private Psr7RequestFactory $psr7RequestFactory,
	)
	{
	}

	/**
	 * @return array<HarvestTimeEntry>
	 */
	public function getData(int $page = 1): array
	{
		$request = $this->psr7RequestFactory->createGETRequest(
			sprintf(self::URL, $this->harvestUserId, self::FROM_DATE, $page),
			[
				'Harvest-Account-Id' => (string) $this->harvestAccountId,
				'Authorization' => 'Bearer ' . $this->harvestAccessToken,
			],
		);

		$response = $this->psr18ClientFactory->getClient()->sendRequest($request);
		$responseContents = (array) Json::decode($response->getBody()->getContents(), true);

		if (array_key_exists('time_entries', $responseContents) === false) {
			return [];
		}

		$items = $responseContents['time_entries'] ?? [];

		if (array_key_exists('next_page', $responseContents) && $responseContents['next_page'] !== null) {
			return array_merge($items, $this->getData((int) $responseContents['next_page']));
		}

		return $items;
	}

}
