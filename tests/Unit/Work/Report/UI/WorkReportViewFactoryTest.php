<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report\UI;

use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\Report\UI\WorkReportViewFactory;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportSanitizer;
use DateTimeImmutable;
use Latte\Engine;
use PHPUnit\Framework\TestCase;

/** @phpstan-import-type WorkReportData from WorkReportBuilder */
class WorkReportViewFactoryTest extends TestCase
{

	public function testUrlOnlyNotesBecomeReadableTitlesAndKeepOriginalNotesAndCommentLinks(): void
	{
		$data = $this->data();
		$view = (new WorkReportViewFactory(
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('synthetic', null),
		))->create($data);
		$entry = $view->projects[0]['entries'][0];
		self::assertSame('', $entry['notes']);
		self::assertSame($data['projects'][0]['entries'][0]['notes'], $entry['sourceNotes']);
		self::assertSame(['Fix login'], $view->projects[0]['topics']);
		self::assertSame('Fix login', $entry['references'][0]['title']);
		self::assertSame('https://gitlab.com/company/app/-/issues/12#note_77', $entry['references'][0]['url']);
		self::assertSame('Uzavřeno', $entry['references'][0]['state']);
		self::assertSame(1.25, $view->hours);
		self::assertSame(1, $view->commentCount);
		self::assertSame('2026-10-27 07:00', $view->generatedAt->format('Y-m-d H:i'));
		self::assertNull($view->aiSummary);
	}

	public function testKeepsOwnWorkNotesUnsupportedLinksAndUnavailableItems(): void
	{
		$data = $this->data();
		$data['status'] = 'partial';
		$data['warnings'] = ['A source is unavailable.'];
		$data['projects'][0]['entries'][0]['notes'] = "Checked login\nhttps://gitlab.com/company/app/-/issues/12\n"
			. 'https://gitlab.com/company/app/-/pipelines/45';
		$data['gitlabItems'][0]['title'] = null;
		$data['gitlabItems'][0]['state'] = null;
		$data['gitlabItems'][0]['status'] = 'unavailable';
		$view = (new WorkReportViewFactory(
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('synthetic', null),
		))->create($data);
		self::assertTrue($view->partial);
		self::assertSame($data['warnings'], $view->warnings);
		self::assertStringContainsString('Checked login', $view->projects[0]['entries'][0]['notes']);
		self::assertStringContainsString('/pipelines/45', $view->projects[0]['entries'][0]['notes']);
		self::assertSame('company/app · Issue #12', $view->projects[0]['entries'][0]['references'][0]['title']);
		self::assertSame('Nedostupné', $view->projects[0]['entries'][0]['references'][0]['state']);
	}

	public function testDoesNotRemoveAUrlWithoutItsStoredGitLabItem(): void
	{
		$data = $this->data();
		$data['gitlabItems'] = [];
		$view = (new WorkReportViewFactory(
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('synthetic', null),
		))->create($data);
		self::assertSame($data['projects'][0]['entries'][0]['notes'], $view->projects[0]['entries'][0]['notes']);
	}

	public function testRedactsLegacySnapshotAtDisplayTimeWithoutChangingStoredData(): void
	{
		$data = $this->data();
		$data['projects'][0]['entries'][0]['notes'] = 'Heslo je legacy-private-password';
		$data['gitlabItems'][0]['notes'][77]['body'] = 'https://share.1password.com/s/legacy-private-link';
		$view = (new WorkReportViewFactory(new GitLabReferenceExtractor(), new WorkReportSanitizer('synthetic', null)))
			->create($data);
		self::assertStringNotContainsString(
			'legacy-private-password',
			$view->projects[0]['entries'][0]['sourceNotes'] ?? '',
		);
		self::assertStringNotContainsString(
			'legacy-private-link',
			$view->context[0]['item']['notes'][77]['body'] ?? '',
		);
		self::assertSame('Heslo je legacy-private-password', $data['projects'][0]['entries'][0]['notes']);
	}

	public function testTaskHoursCombineIssueAndCommentEntriesButKeepDifferentIssuesWithTheSameTitleSeparate(): void
	{
		$data = $this->data();
		$second = $data['projects'][0]['entries'][0];
		$second['id'] = 11;
		$second['hours'] = 0.75;
		$second['gitlabReferences'][0]['noteId'] = null;
		$otherIssue = $data['gitlabItems'][0];
		$otherIssue['key'] = 'company/app/-/issues/13';
		$otherIssue['url'] = 'https://gitlab.com/company/app/-/issues/13';
		$otherIssue['iid'] = 13;
		$third = $second;
		$third['id'] = 12;
		$third['hours'] = 0.5;
		$third['gitlabReferences'][0]['key'] = $otherIssue['key'];
		$data['gitlabItems'][] = $otherIssue;
		$data['projects'][0]['entries'] = [$data['projects'][0]['entries'][0], $second, $third];
		$data['projects'][0]['hours'] = 2.5;
		$view = (new WorkReportViewFactory(new GitLabReferenceExtractor(), new WorkReportSanitizer('synthetic', null)))
			->create($data);
		$project = $view->projects[0];
		self::assertCount(3, $project['entries']);
		self::assertCount(2, $project['tasks']);
		self::assertSame(2.0, $project['tasks'][0]['hours']);
		self::assertSame(2, $project['tasks'][0]['entryCount']);
		self::assertSame('Issue #12', $project['tasks'][0]['references'][0]['label']);
		self::assertSame('https://gitlab.com/company/app/-/issues/12', $project['tasks'][0]['references'][0]['url']);
		self::assertSame('Issue #13', $project['tasks'][1]['references'][0]['label']);
		self::assertSame($project['hours'], array_sum(array_column($project['tasks'], 'hours')));
	}

	public function testHarvestNotesGroupOnlyTheSameActivityAndRetainAllHours(): void
	{
		$data = $this->data();
		$entry = $data['projects'][0]['entries'][0];
		$entry['gitlabReferences'] = [];
		$entry['notes'] = 'Code review';
		$second = $entry;
		$second['id'] = 11;
		$second['hours'] = 0.25;
		$third = $entry;
		$third['id'] = 12;
		$third['notes'] = 'Planning';
		$third['hours'] = 0.5;
		$data['projects'][0]['entries'] = [$entry, $second, $third];
		$view = (new WorkReportViewFactory(new GitLabReferenceExtractor(), new WorkReportSanitizer('synthetic', null)))
			->create($data);
		$tasks = $view->projects[0]['tasks'];
		self::assertSame(['Code review', 'Planning'], array_column($tasks, 'title'));
		self::assertSame([1.5, 0.5], array_column($tasks, 'hours'));
		self::assertSame([2, 1], array_column($tasks, 'entryCount'));
		self::assertSame([], $tasks[0]['references']);
	}

	public function testEntryWithAnIssueAndMrCountsTimeOnceAndIgnoresDuplicateCommentLinksAndLinkOrder(): void
	{
		$data = $this->data();
		$mr = $data['gitlabItems'][0];
		$mr['key'] = 'company/app/-/merge_requests/20';
		$mr['url'] = 'https://gitlab.com/company/app/-/merge_requests/20';
		$mr['type'] = 'merge_requests';
		$mr['iid'] = 20;
		$data['gitlabItems'][] = $mr;
		$entry = $data['projects'][0]['entries'][0];
		$entry['gitlabReferences'][] = ['key' => $mr['key'], 'noteId' => null];
		$entry['gitlabReferences'][] = ['key' => 'company/app/-/issues/12', 'noteId' => null];
		$second = $entry;
		$second['id'] = 11;
		$second['hours'] = 0.75;
		$second['gitlabReferences'] = array_reverse($entry['gitlabReferences']);
		$data['projects'][0]['entries'] = [$entry, $second];
		$view = (new WorkReportViewFactory(new GitLabReferenceExtractor(), new WorkReportSanitizer('synthetic', null)))
			->create($data);
		self::assertCount(1, $view->projects[0]['tasks']);
		$task = $view->projects[0]['tasks'][0];
		self::assertSame(2.0, $task['hours']);
		self::assertSame(2, $task['entryCount']);
		self::assertSame(['Issue #12', 'MR !20'], array_column($task['references'], 'label'));
	}

	public function testPdfHtmlEscapesSourceTextAndIncludesLinkedCommentsWithoutIssueDescription(): void
	{
		$data = $this->data();
		$data['projects'][0]['entries'][0]['notes'] = '<script>alert("entry")</script>';
		$data['gitlabItems'][0]['notes'][77]['body'] = '<img src=x onerror=alert(1)> comment';
		$data['gitlabItems'][0]['description'] = 'Full issue description for web detail only';
		$data['aiSummary'] = [
			'status' => 'complete', 'model' => 'gpt-6-luna', 'generatedAt' => '2026-10-27T07:00:00+01:00',
			'bullets' => [['text' => '<script>alert("summary")</script>', 'entryIds' => [10]]], 'error' => null,
		];
		$view = (new WorkReportViewFactory(
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('synthetic', null),
		))->create($data);
		$latte = new Engine();
		$latte->addFilter(
			'datetimeFormat',
			static fn (DateTimeImmutable $date, string $format): string => $date->format($format),
		);
		$html = $latte->renderToString(
			dirname(__DIR__, 5) . '/src/Work/Report/UI/templates/WorkReport.pdf.latte',
			['view' => $view],
		);
		self::assertStringNotContainsString('<script>', $html);
		self::assertStringContainsString('&lt;script&gt;', $html);
		self::assertStringNotContainsString('<img src=x', $html);
		self::assertStringContainsString('&lt;img src=x', $html);
		self::assertStringContainsString('#note_77', $html);
		self::assertStringNotContainsString('Full issue description for web detail only', $html);
		self::assertStringContainsString('1,25 h', $html);
		self::assertStringContainsString('Issue #12', $html);
		self::assertStringContainsString('Položky: 1 · záznamy: 1', $html);
		self::assertStringContainsString('AI návrh pro standup', $html);
		self::assertStringContainsString('href="#work-entry-10"', $html);
	}

	/** @return WorkReportData */
	private function data(): array
	{
		return [
			'schemaVersion' => 1, 'reportDate' => '2026-10-27', 'generatedAt' => '2026-10-27T06:00:00Z',
			'status' => 'complete', 'period' => ['from' => '2026-10-26', 'to' => '2026-10-26', 'timezone' => 'Europe/Prague'],
			'totals' => ['hours' => 1.25, 'roundedHours' => 1.5, 'entryCount' => 1, 'projectCount' => 1],
			'projects' => [[
				'id' => 1, 'name' => 'Project', 'client' => ['id' => 2, 'name' => 'Client'], 'hours' => 1.25, 'roundedHours' => 1.5,
				'entries' => [[
					'id' => 10, 'date' => '2026-10-26', 'hours' => 1.25, 'roundedHours' => 1.5,
					'task' => ['id' => 3, 'name' => 'Development'], 'notes' => 'https://gitlab.com/company/app/-/work_items/12#note_77',
					'isRunning' => false, 'updatedAt' => '2026-10-26T16:00:00Z',
					'gitlabReferences' => [['key' => 'company/app/-/issues/12', 'noteId' => 77]],
				]],
			]],
			'gitlabItems' => [[
				'key' => 'company/app/-/issues/12', 'projectPath' => 'company/app', 'type' => 'issues', 'iid' => 12,
				'url' => 'https://gitlab.com/company/app/-/issues/12', 'status' => 'loaded', 'title' => 'Fix login',
				'state' => 'closed', 'description' => 'Task description', 'updatedAt' => '2026-10-26T16:00:00Z', 'error' => null,
				'notes' => [77 => ['id' => 77, 'status' => 'loaded', 'body' => 'Login verified', 'author' => 'Developer',
					'createdAt' => '2026-10-26T15:00:00Z', 'updatedAt' => '2026-10-26T16:00:00Z', 'error' => null]],
			]],
			'warnings' => [],
		];
	}

}
