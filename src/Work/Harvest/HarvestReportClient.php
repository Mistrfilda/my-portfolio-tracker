<?php

declare(strict_types = 1);

namespace App\Work\Harvest;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Utils\TypeValidator;
use App\Work\Report\WorkReportException;
use App\Work\Report\WorkReportPeriod;
use InvalidArgumentException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-type NamedResource array{id: int, name: string}
 * @phpstan-type HarvestReportEntry array{
 *     id: int, date: string, hours: float, roundedHours: float, notes: string|null,
 *     project: NamedResource, client: NamedResource, task: NamedResource,
 *     isRunning: bool, updatedAt: string, externalUrl: string|null
 * }
 */
class HarvestReportClient
{

	public function __construct(
		private readonly int $harvestAccountId,
		private readonly int $harvestUserId,
		private readonly string $harvestAccessToken,
		private readonly Psr18ClientFactory $clientFactory,
		private readonly Psr7RequestFactory $requestFactory,
		private readonly LoggerInterface $logger,
	)
	{
	}

	/** @return list<HarvestReportEntry> */
	public function getEntries(WorkReportPeriod $period): array
	{
		$entries = [];
		$page = 1;
		$visited = [];
		do {
			if (isset($visited[$page]) || count($visited) >= 100) {
				throw new WorkReportException('Harvest pagination did not finish.');
			}

			$visited[$page] = true;
			$this->logger->info('Downloading Harvest time entry page', ['page' => $page]);
			$data = $this->getPage($period, $page);
			try {
				foreach (TypeValidator::validateArray($data['time_entries'] ?? null) as $rawEntry) {
					$entry = $this->normalizeEntry(TypeValidator::validateArray($rawEntry), $period);
					if (isset($entries[$entry['id']])) {
						throw new WorkReportException('Harvest returned duplicate time entries; retry the report.');
					}

					$entries[$entry['id']] = $entry;
				}

				if (!array_key_exists('next_page', $data)) {
					throw new InvalidArgumentException('Missing pagination metadata.');
				}

				$page = TypeValidator::validateNullableInt($data['next_page']);
				if ($page !== null && $page < 1) {
					throw new InvalidArgumentException('Invalid next page.');
				}
			} catch (InvalidArgumentException) {
				throw new WorkReportException('Harvest returned invalid time entry data.');
			}
		} while ($page !== null);

		$this->logger->info('Harvest time entries downloaded', [
			'pageCount' => count($visited),
			'entryCount' => count($entries),
		]);
		return array_values($entries);
	}

	/** @return array<mixed> */
	private function getPage(WorkReportPeriod $period, int $page): array
	{
		$query = http_build_query([
			'user_id' => $this->harvestUserId,
			'from' => $period->from->format('Y-m-d'),
			'to' => $period->to->format('Y-m-d'),
			'page' => $page,
			'per_page' => 2000,
		]);
		try {
			$response = $this->clientFactory->getClient([
				'connect_timeout' => 5,
				'timeout' => 30,
				'allow_redirects' => false,
				'http_errors' => false,
			])->sendRequest(
				$this->requestFactory->createGETRequest('https://api.harvestapp.com/v2/time_entries?' . $query, [
					'Authorization' => 'Bearer ' . $this->harvestAccessToken,
					'Harvest-Account-Id' => (string) $this->harvestAccountId,
					'User-Agent' => 'MyPortfolioTracker/WorkReport',
					'Accept' => 'application/json',
				]),
			);
		} catch (ClientExceptionInterface) {
			throw new WorkReportException('Harvest request failed.');
		}

		if ($response->getStatusCode() !== 200) {
			throw new WorkReportException(sprintf('Harvest returned HTTP %d.', $response->getStatusCode()));
		}

		try {
			return TypeValidator::validateArray(Json::decode((string) $response->getBody(), true));
		} catch (JsonException | InvalidArgumentException) {
			throw new WorkReportException('Harvest returned an invalid JSON response.');
		}
	}

	/**
	 * @param array<mixed> $data
	 * @return HarvestReportEntry
	 */
	private function normalizeEntry(array $data, WorkReportPeriod $period): array
	{
		$user = TypeValidator::validateArray($data['user'] ?? null);
		$date = TypeValidator::validateString($data['spent_date'] ?? null);
		$hours = TypeValidator::validateFloat($data['hours'] ?? null);
		$roundedHours = TypeValidator::validateFloat($data['rounded_hours'] ?? null);
		if (
			TypeValidator::validateInt($user['id'] ?? null) !== $this->harvestUserId
			|| preg_match('~^\d{4}-\d{2}-\d{2}$~D', $date) !== 1
			|| $date < $period->from->format('Y-m-d') || $date > $period->to->format('Y-m-d')
			|| !is_finite($hours) || !is_finite($roundedHours) || $hours < 0 || $roundedHours < 0
		) {
			throw new InvalidArgumentException('Unexpected time entry.');
		}

		$externalReference = $data['external_reference'] ?? null;
		$externalUrl = $externalReference === null ? null
			: TypeValidator::validateNullableString(
				TypeValidator::validateArray($externalReference)['permalink'] ?? null,
			);
		return [
			'id' => TypeValidator::validateInt($data['id'] ?? null),
			'date' => $date,
			'hours' => $hours,
			'roundedHours' => $roundedHours,
			'notes' => TypeValidator::validateNullableString($data['notes'] ?? null),
			'project' => $this->namedResource($data['project'] ?? null),
			'client' => $this->namedResource($data['client'] ?? null),
			'task' => $this->namedResource($data['task'] ?? null),
			'isRunning' => TypeValidator::validateBool($data['is_running'] ?? null),
			'updatedAt' => TypeValidator::validateString($data['updated_at'] ?? null),
			'externalUrl' => $externalUrl,
		];
	}

	/** @return NamedResource */
	private function namedResource(mixed $value): array
	{
		$data = TypeValidator::validateArray($value);
		return [
			'id' => TypeValidator::validateInt($data['id'] ?? null),
			'name' => TypeValidator::validateString($data['name'] ?? null),
		];
	}

}
