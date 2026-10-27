<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Work\GitLab\GitLabReference;
use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\GitLab\GitLabReportClient;
use App\Work\Harvest\HarvestReportClient;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportException;
use App\Work\Report\WorkReportPeriod;
use App\Work\Report\WorkReportSanitizer;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** @phpstan-import-type HarvestReportEntry from HarvestReportClient */
class WorkReportBuilderTest extends TestCase
{

	public function testGroupsByProjectIdDeduplicatesGitLabReadsAndCountsEachTimeEntryOnce(): void
	{
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([
			$this->entry(3, 30, 0.3, 0.5, null),
			$this->entry(2, 20, 2.2, 2.25, 'https://gitlab.com/company/project/-/work_items/12#note_77'),
			$this->entry(
				1,
				20,
				1.1,
				1.25,
				"Fixed login, password=private-password\nhttps://gitlab.com/company/project/-/issues/12#note_77 "
				. 'https://gitlab.com/company/project/-/merge_requests/34',
			),
		]);
		$gitlab = $this->createMock(GitLabReportClient::class);
		$gitlab->expects(self::once())->method('assertConfigured');
		$requested = [];
		$gitlab->expects(self::exactly(2))->method('getItem')->willReturnCallback(
			static function (GitLabReference $reference) use (&$requested): array {
				$requested[] = $reference->getKey();
				return [
					'title' => 'Login', 'state' => 'opened', 'description' => 'configured-gitlab-token',
					'updatedAt' => '2026-10-26T15:00:00Z',
				];
			},
		);
		$gitlab->expects(self::once())->method('getNote')->willReturn([
			'id' => 77, 'body' => 'Verified login', 'author' => 'Developer',
			'createdAt' => '2026-10-26T14:00:00Z', 'updatedAt' => '2026-10-26T15:00:00Z',
		]);
		$data = $this->builder($harvest, $gitlab)->build(
			WorkReportPeriod::create('2026-10-27'),
			new ImmutableDateTime('2026-10-27T07:00:00+01:00'),
		);
		self::assertSame('complete', $data['status']);
		self::assertSame(
			['hours' => 3.6, 'roundedHours' => 4.0, 'entryCount' => 3, 'projectCount' => 2],
			$data['totals'],
		);
		self::assertSame([20, 30], array_column($data['projects'], 'id'));
		self::assertSame([1, 2], array_column($data['projects'][0]['entries'], 'id'));
		self::assertSame(3.3, $data['projects'][0]['hours']);
		self::assertCount(2, $data['projects'][0]['entries'][0]['gitlabReferences']);
		self::assertCount(2, array_unique($requested));
		self::assertSame('Verified login', $data['gitlabItems'][0]['notes'][0]['body']);
		self::assertSame([], $data['warnings']);
		self::assertStringNotContainsString('private-password', Json::encode($data));
		self::assertStringNotContainsString('configured-gitlab-token', Json::encode($data));
	}

	public function testUnavailableGitLabItemIsExplicitAndPreservesHarvestWork(): void
	{
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([
			$this->entry(1, 20, 1.0, 1.0, 'https://gitlab.com/company/project/-/issues/12'),
			$this->entry(2, 20, 2.0, 2.0, 'https://gitlab.com/company/project/-/issues/12'),
		]);
		$gitlab = $this->createMock(GitLabReportClient::class);
		$gitlab->expects(self::once())->method('assertConfigured');
		$gitlab->expects(self::once())->method('getItem')->willThrowException(
			new WorkReportException('GitLab returned HTTP 404.'),
		);
		$data = $this->builder($harvest, $gitlab)->build(
			WorkReportPeriod::create('2026-10-27'),
			new ImmutableDateTime(),
		);
		self::assertSame('partial', $data['status']);
		self::assertSame(3.0, $data['totals']['hours']);
		self::assertCount(2, $data['projects'][0]['entries']);
		self::assertSame('unavailable', $data['gitlabItems'][0]['status']);
		self::assertNull($data['gitlabItems'][0]['title']);
		self::assertSame(['company/project/-/issues/12: GitLab returned HTTP 404.'], $data['warnings']);
	}

	public function testStructuredExternalReferenceIsUsedAndRunningTimerIsFlagged(): void
	{
		$entry = $this->entry(1, 20, 1.0, 1.0, null);
		$entry['externalUrl'] = 'https://gitlab.com/company/project/-/issues/12';
		$entry['isRunning'] = true;
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([$entry]);
		$gitlab = $this->createStub(GitLabReportClient::class);
		$gitlab->method('getItem')->willReturn([
			'title' => 'Login', 'state' => 'opened', 'description' => null, 'updatedAt' => '2026-10-26T15:00:00Z',
		]);
		$data = $this->builder($harvest, $gitlab)->build(
			WorkReportPeriod::create('2026-10-27'),
			new ImmutableDateTime(),
		);
		self::assertSame(
			'company/project/-/issues/12',
			$data['projects'][0]['entries'][0]['gitlabReferences'][0]['key'],
		);
		self::assertSame(['Harvest entry 1 still has a running timer.'], $data['warnings']);
	}

	public function testEmptyDayProducesAnExplicitEmptyReport(): void
	{
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([]);
		$data = $this->builder($harvest, $this->createStub(GitLabReportClient::class))
			->build(WorkReportPeriod::create('2026-10-27'), new ImmutableDateTime());
		self::assertSame('complete', $data['status']);
		self::assertSame(0, $data['totals']['entryCount']);
		self::assertSame([], $data['projects']);
		self::assertSame([], $data['gitlabItems']);
	}

	public function testPasswordAssignmentFormatsAreRedactedBeforeTheSnapshotLeavesTheBuilder(): void
	{
		$entries = [];
		foreach ([
			'heslo: synthetic-only-secret',
			'heslo = synthetic-only-secret',
			'**password:** synthetic-only-secret',
			"'password' => 'synthetic-only-secret'",
		] as $i => $source) {
			$entries[] = $this->entry($i + 1, 20, 1.0, 1.0, 'Kontrola přihlášení. ' . $source);
		}

		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn($entries);
		$gitlab = $this->createMock(GitLabReportClient::class);
		$gitlab->expects(self::once())->method('assertConfigured');
		$gitlab->expects(self::never())->method('getItem');
		$data = $this->builder($harvest, $gitlab)->build(
			WorkReportPeriod::create('2026-10-27'),
			new ImmutableDateTime(),
		);
		self::assertStringNotContainsString('synthetic-only-secret', Json::encode($data));
		self::assertSame(4, $data['totals']['entryCount']);
		self::assertSame(4.0, $data['totals']['hours']);
		foreach ($data['projects'][0]['entries'] as $entry) {
			self::assertStringContainsString('[REDACTED]', $entry['notes'] ?? '');
			self::assertStringContainsString('Kontrola přihlášení.', $entry['notes'] ?? '');
		}
	}

	public function testUnsupportedGitLabUrlMakesReportPartialWithoutLosingHarvestEntry(): void
	{
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([
			$this->entry(1, 20, 1.0, 1.0, 'https://gitlab.com/company/project/-/pipelines/56'),
		]);
		$gitlab = $this->createMock(GitLabReportClient::class);
		$gitlab->expects(self::once())->method('assertConfigured');
		$gitlab->expects(self::never())->method('getItem');
		$data = $this->builder($harvest, $gitlab)->build(
			WorkReportPeriod::create('2026-10-27'),
			new ImmutableDateTime(),
		);
		self::assertSame('partial', $data['status']);
		self::assertSame(1.0, $data['totals']['hours']);
		self::assertCount(1, $data['projects'][0]['entries']);
		self::assertSame(['Harvest entry 1 contains 1 unsupported GitLab links.'], $data['warnings']);
	}

	private function builder(HarvestReportClient $harvest, GitLabReportClient $gitlab): WorkReportBuilder
	{
		return new WorkReportBuilder(
			$harvest,
			$gitlab,
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('configured-harvest-token', 'configured-gitlab-token'),
			new NullLogger(),
		);
	}

	/** @return HarvestReportEntry */
	private function entry(int $id, int $projectId, float $hours, float $roundedHours, string|null $notes): array
	{
		return [
			'id' => $id, 'date' => '2026-10-26', 'hours' => $hours, 'roundedHours' => $roundedHours, 'notes' => $notes,
			'project' => ['id' => $projectId, 'name' => 'Same name'], 'client' => ['id' => 40, 'name' => 'Client'],
			'task' => ['id' => 50, 'name' => 'Development'], 'isRunning' => false,
			'updatedAt' => '2026-10-26T15:00:00Z', 'externalUrl' => null,
		];
	}

}
