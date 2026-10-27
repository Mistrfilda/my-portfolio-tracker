<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\GitLab;

use App\Work\GitLab\GitLabReferenceExtractor;
use PHPUnit\Framework\TestCase;

class GitLabReferenceExtractorTest extends TestCase
{

	public function testResolvesProjectWorkItemUrlsAndDirectCommentsThroughIssuesApi(): void
	{
		$extractor = new GitLabReferenceExtractor();
		$workItemUrl = 'https://gitlab.com/company/team/project/-/work_items/12#note_77';
		$references = $extractor->extract($workItemUrl);
		self::assertCount(1, $references);
		self::assertSame('issues', $references[0]->resourceType);
		self::assertSame(12, $references[0]->iid);
		self::assertSame(77, $references[0]->noteId);
		self::assertSame('projects/company%2Fteam%2Fproject/issues/12', $references[0]->getApiPath());
		self::assertCount(
			1,
			$extractor->extract($workItemUrl . ' https://gitlab.com/company/team/project/-/issues/12#note_77'),
		);
	}

	public function testCountsUnsupportedGitLabUrlsWithoutIncludingOtherHosts(): void
	{
		$extractor = new GitLabReferenceExtractor();
		self::assertSame(1, $extractor->countUnsupportedUrls(
			'https://gitlab.com/company/project/-/work_items/12#note_77 '
			. 'https://gitlab.com/company/project/-/merge_requests/34 '
			. 'https://gitlab.com/company/project/-/pipelines/56 '
			. 'https://example.com/company/project/-/issues/1',
		));
	}

	public function testExtractsNestedProjectsNotesAndMergeRequestsAndRemovesQueryStrings(): void
	{
		$references = (new GitLabReferenceExtractor())->extract(
			'Investigated [issue](https://gitlab.com/company/team/project/-/issues/12?token=private#note_77). '
			. 'Reviewed https://gitlab.com/company/repository/-/merge_requests/34/diffs '
			. 'Repeated https://gitlab.com/company/team/project/-/issues/12#note_77',
		);
		self::assertCount(2, $references);
		self::assertSame('company/team/project', $references[0]->projectPath);
		self::assertSame(77, $references[0]->noteId);
		self::assertSame('https://gitlab.com/company/team/project/-/issues/12', $references[0]->getUrl());
		self::assertSame('projects/company%2Fteam%2Fproject/issues/12', $references[0]->getApiPath());
		self::assertSame('merge_requests', $references[1]->resourceType);
		self::assertSame(34, $references[1]->iid);
	}

	public function testRejectsOtherHostsSchemesAndTraversal(): void
	{
		self::assertSame([], (new GitLabReferenceExtractor())->extract(
			'https://gitlab.com.evil.example/company/project/-/issues/1 '
			. 'http://gitlab.com/company/project/-/issues/1 '
			. 'https://gitlab.com@evil.example/company/project/-/issues/1 '
			. 'https://gitlab.com/company/../project/-/issues/1 '
			. 'https://gitlab.com/company/project/-/issues/0',
		));
	}

}
