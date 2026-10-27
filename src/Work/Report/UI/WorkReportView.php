<?php

declare(strict_types = 1);

namespace App\Work\Report\UI;

use App\Work\Report\WorkReportAiSummaryGenerator;
use App\Work\Report\WorkReportBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @phpstan-type ViewReference array{title: string, label: string, url: string, state: string}
 * @phpstan-type ViewEntry array{
 *     id: int, date: ImmutableDateTime, hours: float, taskName: string, notes: string,
 *     sourceNotes: string|null, isRunning: bool, references: list<ViewReference>
 * }
 * @phpstan-type ViewTask array{title: string, hours: float, entryCount: int, references: list<ViewReference>}
 * @phpstan-type ViewProject array{
 *     name: string, clientName: string, hours: float, topics: list<string>, tasks: list<ViewTask>, entries: list<ViewEntry>
 * }
 * @phpstan-type ViewContext array{item: ReportGitLabItem, label: string, title: string, state: string}
 * @phpstan-type ViewAiSummary array{status: string, model: string, generatedAt: ImmutableDateTime, bullets: list<SummaryBullet>}
 * @phpstan-import-type ReportGitLabItem from WorkReportBuilder
 * @phpstan-import-type SummaryBullet from WorkReportAiSummaryGenerator
 */
final readonly class WorkReportView
{

	public int $commentCount;

	/**
	 * @param list<ViewProject> $projects
	 * @param list<ViewContext> $context
	 * @param list<string> $warnings
	 * @param ViewAiSummary|null $aiSummary
	 */
	public function __construct(
		public ImmutableDateTime $reportDate,
		public ImmutableDateTime $from,
		public ImmutableDateTime $to,
		public ImmutableDateTime $generatedAt,
		public float $hours,
		public int $entryCount,
		public bool $partial,
		public array $projects,
		public array $context,
		public array $warnings,
		public array|null $aiSummary = null,
	)
	{
		$this->commentCount = array_sum(
			array_map(static fn (array $context): int => count($context['item']['notes']), $context),
		);
	}

}
