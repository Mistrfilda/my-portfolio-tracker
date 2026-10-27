<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Work\Report\WorkReportAiInputFactory;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportSanitizer;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use PHPUnit\Framework\TestCase;

/** @phpstan-import-type WorkReportData from WorkReportBuilder */
class WorkReportAiInputFactoryTest extends TestCase
{

	public function testSelectsOnlyUsefulFieldsAndRemovesCredentialsUrlsAndEmails(): void
	{
		$data = $this->data();
		$input = (new WorkReportAiInputFactory(new WorkReportSanitizer('configured-harvest', 'configured-gitlab')))
			->create($data);
		$encoded = Json::encode($input);
		foreach ([
			'private-password', 'configured-harvest', 'configured-gitlab', 'https://', 'developer@example.com',
			'private-description', 'private-comment', 'private-author', 'private-group/app', 'private-warning',
			'description', 'client', 'updatedAt', 'state',
		] as $excluded) {
			self::assertStringNotContainsString($excluded, $encoded);
		}

		self::assertStringContainsString('Checked login', $encoded);
		self::assertStringContainsString('Abc123%2BFakeIdentifier%3D', $encoded);
		self::assertSame(
			['type' => 'Issue', 'iid' => 12, 'title' => 'Login'],
			$input['projects'][0]['entries'][0]['references'][0],
		);
		self::assertSame(10, $input['projects'][0]['entries'][0]['id']);
		self::assertSame(1.25, $input['projects'][0]['entries'][0]['hours']);
		self::assertSame($data['period'], $input['period']);
		self::assertStringContainsString('private-password', $data['projects'][0]['entries'][0]['notes'] ?? '');
	}

	public function testLimitsFreeTextAndDeduplicatesIssueAndCommentReferences(): void
	{
		$data = $this->data();
		$data['projects'][0]['entries'][0]['notes'] = str_repeat('č', 2000);
		$data['projects'][0]['entries'][0]['gitlabReferences'][] = ['key' => 'private-group/app/-/issues/12', 'noteId' => null];
		$data['gitlabItems'][0]['title'] = str_repeat('č', 500);
		$input = (new WorkReportAiInputFactory(new WorkReportSanitizer('synthetic', null)))->create($data);
		$entry = $input['projects'][0]['entries'][0];
		self::assertLessThanOrEqual(1000, Strings::length($entry['notes']));
		self::assertCount(1, $entry['references']);
		self::assertLessThanOrEqual(300, Strings::length($entry['references'][0]['title'] ?? ''));
	}

	public function testPasswordAssignmentFormatsCannotReachAiFromLegacyNotesOrTitles(): void
	{
		$factory = new WorkReportAiInputFactory(new WorkReportSanitizer('synthetic', null));
		foreach ([
			'heslo: synthetic-only-secret',
			'heslo = synthetic-only-secret',
			'**password:** synthetic-only-secret',
			"'password' => 'synthetic-only-secret'",
		] as $source) {
			$data = $this->data();
			$data['projects'][0]['entries'][0]['notes'] = 'Kontrola přihlášení. ' . $source;
			$data['gitlabItems'][0]['title'] = 'Úprava konfigurace: ' . $source;
			$input = $factory->create($data);
			self::assertStringNotContainsString('synthetic-only-secret', Json::encode($input));
			self::assertStringContainsString('[REDACTED]', $input['projects'][0]['entries'][0]['notes']);
			self::assertStringContainsString('Kontrola přihlášení.', $input['projects'][0]['entries'][0]['notes']);
			self::assertStringContainsString(
				'synthetic-only-secret',
				$data['projects'][0]['entries'][0]['notes'] ?? '',
			);
		}
	}

	/** @return WorkReportData */
	private function data(): array
	{
		return [
			'schemaVersion' => 1, 'reportDate' => '2026-10-27', 'generatedAt' => '2026-10-27T07:00:00+01:00', 'status' => 'partial',
			'period' => ['from' => '2026-10-26', 'to' => '2026-10-26', 'timezone' => 'Europe/Prague'],
			'totals' => ['hours' => 1.25, 'roundedHours' => 1.25, 'entryCount' => 1, 'projectCount' => 1],
			'projects' => [[
				'id' => 1, 'name' => 'Project', 'client' => ['id' => 2, 'name' => 'Client'], 'hours' => 1.25, 'roundedHours' => 1.25,
				'entries' => [[
					'id' => 10, 'date' => '2026-10-26', 'hours' => 1.25, 'roundedHours' => 1.25,
					'task' => ['id' => 3, 'name' => 'Development'], 'isRunning' => false, 'updatedAt' => '2026-10-26T15:00:00Z',
					'notes' => 'Checked login. password=private-password configured-harvest configured-gitlab '
						. 'https://gitlab.com/private-group/app/-/issues/12 developer@example.com '
						. 'CIDC testovacího prostředí je Abc123%2BFakeIdentifier%3D',
					'gitlabReferences' => [['key' => 'private-group/app/-/issues/12', 'noteId' => 77]],
				]],
			]],
			'gitlabItems' => [[
				'key' => 'private-group/app/-/issues/12', 'projectPath' => 'private-group/app', 'type' => 'issues', 'iid' => 12,
				'url' => 'https://gitlab.com/private-group/app/-/issues/12', 'status' => 'loaded', 'title' => 'Login',
				'state' => 'closed', 'description' => 'private-description', 'updatedAt' => '2026-10-26T16:00:00Z', 'error' => null,
				'notes' => [77 => ['id' => 77, 'status' => 'loaded', 'body' => 'private-comment', 'author' => 'private-author',
					'createdAt' => '2026-10-26T15:00:00Z', 'updatedAt' => '2026-10-26T16:00:00Z', 'error' => null]],
			]],
			'warnings' => ['private-warning'],
		];
	}

}
