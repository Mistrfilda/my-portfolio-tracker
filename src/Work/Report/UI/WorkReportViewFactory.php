<?php

declare(strict_types = 1);

namespace App\Work\Report\UI;

use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportSanitizer;
use DateTimeZone;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/** @phpstan-import-type WorkReportData from WorkReportBuilder */
class WorkReportViewFactory
{

	public function __construct(
		private readonly GitLabReferenceExtractor $referenceExtractor,
		private readonly WorkReportSanitizer $sanitizer,
	)
	{
	}

	/** @param WorkReportData $data */
	public function create(array $data): WorkReportView
	{
		$data = $this->sanitizer->sanitizeData($data);
		$timezone = new DateTimeZone($data['period']['timezone']);
		$context = [];
		foreach ($data['gitlabItems'] as $item) {
			$label = ($item['type'] === 'merge_requests' ? 'MR !' : 'Issue #') . $item['iid'];
			$context[$item['key']] = [
				'item' => $item,
				'label' => $label,
				'title' => $item['title'] ?? $item['projectPath'] . ' · ' . $label,
				'state' => match ($item['state']) {
					'opened' => 'Otevřeno',
					'closed' => 'Uzavřeno',
					'merged' => 'Sloučeno',
					'locked' => 'Uzamčeno',
					default => $item['state'] ?? 'Nedostupné',
				},
			];
		}

		$projects = [];
		foreach ($data['projects'] as $project) {
			$entries = [];
			$topics = [];
			$tasks = [];
			foreach ($project['entries'] as $entry) {
				$references = [];
				$taskReferences = [];
				foreach ($entry['gitlabReferences'] as $reference) {
					$item = $context[$reference['key']] ?? null;
					if ($item === null) {
						continue;
					}

					$references[] = [
						'title' => $item['title'],
						'label' => $item['label'] . ($reference['noteId'] === null ? '' : ' · komentář'),
						'url' => $item['item']['url'] . ($reference['noteId'] === null ? '' : '#note_' . $reference['noteId']),
						'state' => $item['state'],
					];
					$topics[] = $item['title'];
					$taskReferences[$reference['key']] = [
						'title' => $item['title'],
						'label' => $item['label'],
						'url' => $item['item']['url'],
						'state' => $item['state'],
					];
				}

				$notes = $this->removeResolvedUrls($entry['notes'] ?? '', array_keys($context));
				if ($references === []) {
					$topics[] = $notes !== '' ? $notes : $entry['task']['name'];
				}

				ksort($taskReferences);
				$taskKey = $taskReferences === []
					? 'harvest:' . $entry['task']['id'] . ':' . ($notes !== '' ? $notes : $entry['task']['name'])
					: 'gitlab:' . implode('|', array_keys($taskReferences));
				$tasks[$taskKey] ??= [
					'title' => $notes !== '' ? $notes : $entry['task']['name'],
					'hours' => 0.0,
					'entryCount' => 0,
					'references' => array_values($taskReferences),
				];
				$tasks[$taskKey]['hours'] = round($tasks[$taskKey]['hours'] + $entry['hours'], 4);
				$tasks[$taskKey]['entryCount']++;

				$entries[] = [
					'id' => $entry['id'],
					'date' => new ImmutableDateTime($entry['date'], $timezone),
					'hours' => $entry['hours'],
					'taskName' => $entry['task']['name'],
					'notes' => $notes,
					'sourceNotes' => $entry['notes'],
					'isRunning' => $entry['isRunning'],
					'references' => $references,
				];
			}

			$tasks = array_values($tasks);
			usort($tasks, static fn (array $left, array $right): int => $right['hours'] <=> $left['hours']);

			$projects[] = [
				'name' => $project['name'],
				'clientName' => $project['client']['name'],
				'hours' => $project['hours'],
				'topics' => array_values(array_unique($topics)),
				'tasks' => $tasks,
				'entries' => $entries,
			];
		}

		usort($projects, static fn (array $left, array $right): int => $right['hours'] <=> $left['hours']);
		$summary = $data['aiSummary'] ?? null;
		$aiSummary = $summary === null ? null : [
			'status' => $summary['status'],
			'model' => $summary['model'],
			'generatedAt' => (new ImmutableDateTime($summary['generatedAt']))->setTimezone($timezone),
			'bullets' => $summary['bullets'],
		];

		return new WorkReportView(
			new ImmutableDateTime($data['reportDate'], $timezone),
			new ImmutableDateTime($data['period']['from'], $timezone),
			new ImmutableDateTime($data['period']['to'], $timezone),
			(new ImmutableDateTime($data['generatedAt']))->setTimezone($timezone),
			$data['totals']['hours'],
			$data['totals']['entryCount'],
			$data['status'] !== 'complete',
			$projects,
			array_values($context),
			$data['warnings'],
			$aiSummary,
		);
	}

	/** @param list<string> $resolvedKeys */
	private function removeResolvedUrls(string $notes, array $resolvedKeys): string
	{
		return trim((string) preg_replace_callback(
			'~https://gitlab\.com/[^\s<>"\']+~i',
			function (array $match) use ($resolvedKeys): string {
				$reference = $this->referenceExtractor->extract($match[0])[0] ?? null;
				return $reference !== null && in_array($reference->getKey(), $resolvedKeys, true) ? '' : $match[0];
			},
			$notes,
		), " \t\n\r\0\x0B-()[]");
	}

}
