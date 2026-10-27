<?php

declare(strict_types = 1);

namespace App\Work\Report;

use Nette\Utils\Strings;

/**
 * @phpstan-type AiInput array{
 *     reportDate: string, period: array{from: string, to: string, timezone: string}, sourceStatus: string,
 *     projects: list<array{id: int, name: string, hours: float, entries: list<array{
 *         id: int, date: string, hours: float, task: string, notes: string,
 *         references: list<array{type: string, iid: int, title: string|null}>
 *     }>}>
 * }
 * @phpstan-import-type WorkReportData from WorkReportBuilder
 */
class WorkReportAiInputFactory
{

	public function __construct(private readonly WorkReportSanitizer $sanitizer)
	{
	}

	/** @param WorkReportData $data
	 * @return AiInput
	 */
	public function create(array $data): array
	{
		$data = $this->sanitizer->sanitizeData($data);
		$items = [];
		foreach ($data['gitlabItems'] as $item) {
			$items[$item['key']] = [
				'type' => $item['type'] === 'merge_requests' ? 'MR' : 'Issue',
				'iid' => $item['iid'],
				'title' => $item['title'] === null ? null : $this->cleanText($item['title'], 300),
			];
		}

		$projects = [];
		foreach ($data['projects'] as $project) {
			$entries = [];
			foreach ($project['entries'] as $entry) {
				$references = [];
				foreach ($entry['gitlabReferences'] as $reference) {
					if (isset($items[$reference['key']])) {
						$references[$reference['key']] = $items[$reference['key']];
					}
				}

				$entries[] = [
					'id' => $entry['id'],
					'date' => $entry['date'],
					'hours' => $entry['hours'],
					'task' => $this->cleanText($entry['task']['name'], 100),
					'notes' => $this->cleanText($entry['notes'] ?? '', 1000),
					'references' => array_values($references),
				];
			}

			$projects[] = [
				'id' => $project['id'],
				'name' => $this->cleanText($project['name'], 200),
				'hours' => $project['hours'],
				'entries' => $entries,
			];
		}

		return [
			'reportDate' => $data['reportDate'],
			'period' => $data['period'],
			'sourceStatus' => $data['status'],
			'projects' => $projects,
		];
	}

	public function cleanText(string $text, int $limit = 450): string
	{
		$text = $this->sanitizer->sanitize($text);
		$text = Strings::replace($text, '~\b(?:https?://|www\.)[^\s<>"\']+~i', '[odkaz vynechán]');
		$text = Strings::replace($text, '~\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b~i', '[email vynechán]');
		return Strings::truncate(trim($text), $limit);
	}

}
