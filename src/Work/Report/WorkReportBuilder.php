<?php

declare(strict_types = 1);

namespace App\Work\Report;

use App\Work\GitLab\GitLabReference;
use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\GitLab\GitLabReportClient;
use App\Work\Harvest\HarvestReportClient;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-type ReportEntry array{
 *     id: int, date: string, hours: float, roundedHours: float, task: NamedResource, notes: string|null,
 *     isRunning: bool, updatedAt: string, gitlabReferences: list<array{key: string, noteId: int|null}>
 * }
 * @phpstan-type ReportProject array{
 *     id: int, name: string, client: NamedResource, hours: float, roundedHours: float, entries: list<ReportEntry>
 * }
 * @phpstan-type ReportNote array{
 *     id: int, status: string, body: string|null, author: string|null, createdAt: string|null, updatedAt: string|null, error: string|null
 * }
 * @phpstan-type ReportGitLabItem array{
 *     key: string, projectPath: string, type: string, iid: int, url: string, status: string,
 *     title: string|null, state: string|null, description: string|null, updatedAt: string|null,
 *     error: string|null, notes: array<int, ReportNote>
 * }
 * @phpstan-type WorkReportData array{
 *     schemaVersion: int, reportDate: string, generatedAt: string, status: string,
 *     period: array{from: string, to: string, timezone: string},
 *     totals: array{hours: float, roundedHours: float, entryCount: int, projectCount: int},
 *     projects: list<ReportProject>, gitlabItems: list<ReportGitLabItem>, warnings: list<string>, aiSummary?: AiSummary
 * }
 * @phpstan-import-type NamedResource from HarvestReportClient
 * @phpstan-import-type AiSummary from WorkReportAiSummaryGenerator
 */
class WorkReportBuilder
{

	public function __construct(
		private readonly HarvestReportClient $harvestClient,
		private readonly GitLabReportClient $gitlabClient,
		private readonly GitLabReferenceExtractor $referenceExtractor,
		private readonly WorkReportSanitizer $sanitizer,
		private readonly LoggerInterface $logger,
	)
	{
	}

	/** @return WorkReportData */
	public function build(WorkReportPeriod $period, ImmutableDateTime $now): array
	{
		$this->gitlabClient->assertConfigured();
		$this->logger->info('Downloading Harvest data for work report');
		$entries = $this->harvestClient->getEntries($period);
		$this->logger->info('Enriching work report with GitLab data', ['entryCount' => count($entries)]);
		usort(
			$entries,
			static fn (array $left, array $right): int => [$left['date'], $left['id']] <=> [$right['date'], $right['id']],
		);
		$projects = [];
		$items = [];
		$warnings = [];
		$totalHours = 0.0;
		$totalRoundedHours = 0.0;

		foreach ($entries as $entry) {
			$projectId = $entry['project']['id'];
			$projects[$projectId] ??= [
				'id' => $projectId,
				'name' => $entry['project']['name'],
				'client' => $entry['client'],
				'hours' => 0.0,
				'roundedHours' => 0.0,
				'entries' => [],
			];
			$entryReferences = [];
			$referenceText = ($entry['notes'] ?? '') . "\n" . ($entry['externalUrl'] ?? '');
			$references = $this->referenceExtractor->extract($referenceText);
			$unsupportedLinkCount = $this->referenceExtractor->countUnsupportedUrls($referenceText);
			if ($unsupportedLinkCount > 0) {
				$this->logger->warning('Harvest entry contains unsupported GitLab links', [
					'entryId' => $entry['id'],
					'linkCount' => $unsupportedLinkCount,
				]);
				$warnings[] = sprintf(
					'Harvest entry %d contains %d unsupported GitLab links.',
					$entry['id'],
					$unsupportedLinkCount,
				);
			}

			foreach ($references as $reference) {
				$key = $reference->getKey();
				if (!isset($items[$key])) {
					$this->logger->info('Downloading GitLab work item', [
						'itemNumber' => count($items) + 1,
						'type' => $reference->resourceType,
						'iid' => $reference->iid,
					]);
					$items[$key] = $this->loadItem($reference);
					if ($items[$key]['error'] !== null) {
						$this->logger->warning('GitLab work item unavailable', [
							'itemNumber' => count($items),
							'type' => $reference->resourceType,
							'iid' => $reference->iid,
						]);
						$warnings[] = $key . ': ' . $items[$key]['error'];
					}
				}

				if ($reference->noteId !== null && !isset($items[$key]['notes'][$reference->noteId])) {
					$this->logger->info('Downloading GitLab comment', ['noteId' => $reference->noteId]);
					$note = $this->loadNote($reference);
					$items[$key]['notes'][$reference->noteId] = $note;
					if ($note['error'] !== null) {
						$this->logger->warning('GitLab comment unavailable', ['noteId' => $reference->noteId]);
						$warnings[] = $key . '#note_' . $reference->noteId . ': ' . $note['error'];
					}
				}

				$entryReferences[] = ['key' => $key, 'noteId' => $reference->noteId];
			}

			$projects[$projectId]['entries'][] = [
				'id' => $entry['id'],
				'date' => $entry['date'],
				'hours' => $entry['hours'],
				'roundedHours' => $entry['roundedHours'],
				'task' => $entry['task'],
				'notes' => $entry['notes'],
				'isRunning' => $entry['isRunning'],
				'updatedAt' => $entry['updatedAt'],
				'gitlabReferences' => $entryReferences,
			];
			$projects[$projectId]['hours'] = round($projects[$projectId]['hours'] + $entry['hours'], 4);
			$projects[$projectId]['roundedHours'] = round(
				$projects[$projectId]['roundedHours'] + $entry['roundedHours'],
				4,
			);
			$totalHours = round($totalHours + $entry['hours'], 4);
			$totalRoundedHours = round($totalRoundedHours + $entry['roundedHours'], 4);
			if ($entry['isRunning']) {
				$warnings[] = sprintf('Harvest entry %d still has a running timer.', $entry['id']);
			}
		}

		ksort($projects);
		ksort($items);
		foreach ($items as &$item) {
			ksort($item['notes']);
			$item['notes'] = array_values($item['notes']);
		}

		unset($item);
		$data = [
			'schemaVersion' => 1,
			'reportDate' => $period->reportDate->format('Y-m-d'),
			'generatedAt' => $now->format('c'),
			'status' => count($warnings) === 0 ? 'complete' : 'partial',
			'period' => [
				'from' => $period->from->format('Y-m-d'),
				'to' => $period->to->format('Y-m-d'),
				'timezone' => WorkReportPeriod::TIMEZONE,
			],
			'totals' => [
				'hours' => $totalHours,
				'roundedHours' => $totalRoundedHours,
				'entryCount' => count($entries),
				'projectCount' => count($projects),
			],
			'projects' => array_values($projects),
			'gitlabItems' => array_values($items),
			'warnings' => $warnings,
		];
		$this->logger->info('Work report data assembled', [
			'entryCount' => count($entries),
			'projectCount' => count($projects),
			'gitlabItemCount' => count($items),
			'gitlabCommentCount' => array_sum(array_map(static fn (array $item): int => count($item['notes']), $items)),
			'warningCount' => count($warnings),
		]);
		return $this->sanitizer->sanitizeData($data);
	}

	/** @return ReportGitLabItem */
	private function loadItem(GitLabReference $reference): array
	{
		$item = [
			'key' => $reference->getKey(),
			'projectPath' => $reference->projectPath,
			'type' => $reference->resourceType,
			'iid' => $reference->iid,
			'url' => $reference->getUrl(),
			'status' => 'unavailable',
			'title' => null,
			'state' => null,
			'description' => null,
			'updatedAt' => null,
			'error' => null,
			'notes' => [],
		];
		try {
			return array_merge($item, $this->gitlabClient->getItem($reference), ['status' => 'loaded']);
		} catch (WorkReportException $exception) {
			$item['error'] = $exception->getMessage();
			return $item;
		}
	}

	/** @return ReportNote */
	private function loadNote(GitLabReference $reference): array
	{
		try {
			return array_merge($this->gitlabClient->getNote($reference), ['status' => 'loaded', 'error' => null]);
		} catch (WorkReportException $exception) {
			return [
				'id' => $reference->noteId ?? 0,
				'status' => 'unavailable',
				'body' => null,
				'author' => null,
				'createdAt' => null,
				'updatedAt' => null,
				'error' => $exception->getMessage(),
			];
		}
	}

}
