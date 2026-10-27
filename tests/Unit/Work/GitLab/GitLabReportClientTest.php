<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\GitLab;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Work\GitLab\GitLabReference;
use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\GitLab\GitLabReportClient;
use App\Work\Report\WorkReportException;
use GuzzleHttp\Exception\ConnectException;
use Nette\Utils\Json;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class GitLabReportClientTest extends TestCase
{

	public function testWorkItemUrlLoadsIssueAndItsReferencedNote(): void
	{
		$references = (new GitLabReferenceExtractor())->extract(
			'https://gitlab.com/company/team/project/-/work_items/12#note_77',
		);
		self::assertCount(1, $references);
		$http = $this->createMock(ClientInterface::class);
		$http->expects(self::exactly(2))->method('sendRequest')->willReturnCallback(
			static function (RequestInterface $request): Response {
				$baseUrl = 'https://gitlab.com/api/v4/projects/company%2Fteam%2Fproject/issues/12';
				if ((string) $request->getUri() === $baseUrl . '/notes/77') {
					return new Response(200, [], Json::encode([
						'id' => 77, 'body' => 'Verified work item', 'author' => ['name' => 'Developer'],
						'created_at' => '2026-10-26T14:00:00Z', 'updated_at' => '2026-10-26T14:00:00Z',
					]));
				}

				self::assertSame($baseUrl, (string) $request->getUri());
				return new Response(200, [], Json::encode([
					'title' => 'Fix work item', 'state' => 'opened', 'description' => 'Details',
					'updated_at' => '2026-10-26T14:00:00Z', 'issue_type' => 'task',
				]));
			},
		);
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($http);
		$client = new GitLabReportClient('synthetic-gitlab-token', $factory, new Psr7RequestFactory());
		self::assertSame('Fix work item', $client->getItem($references[0])['title']);
		self::assertSame('Verified work item', $client->getNote($references[0])['body']);
	}

	public function testReadsItemAndDirectlyReferencedCommentThroughEncodedApiPaths(): void
	{
		$http = $this->createMock(ClientInterface::class);
		$http->expects(self::exactly(2))->method('sendRequest')->willReturnCallback(
			static function (RequestInterface $request): Response {
				self::assertSame('GET', $request->getMethod());
				self::assertSame('synthetic-gitlab-token', $request->getHeaderLine('PRIVATE-TOKEN'));
				$url = (string) $request->getUri();
				if (str_ends_with($url, '/notes/77')) {
					self::assertSame(
						'https://gitlab.com/api/v4/projects/company%2Fteam%2Fproject/merge_requests/12/notes/77',
						$url,
					);
					return new Response(200, [], Json::encode([
						'id' => 77, 'body' => 'Verified fix', 'author' => ['name' => 'Developer', 'email' => 'private@example.com'],
						'created_at' => '2026-10-26T14:00:00Z', 'updated_at' => '2026-10-26T14:00:00Z',
					]));
				}

				self::assertSame('https://gitlab.com/api/v4/projects/company%2Fteam%2Fproject/merge_requests/12', $url);
				return new Response(200, [], Json::encode([
					'title' => 'Fix login', 'state' => 'opened', 'description' => 'Details', 'updated_at' => '2026-10-26T14:00:00Z',
					'private_extra_data' => 'not needed for report',
				]));
			},
		);
		$factory = $this->createMock(Psr18ClientFactory::class);
		$factory->expects(self::exactly(2))->method('getClient')->with([
			'connect_timeout' => 5, 'timeout' => 30, 'allow_redirects' => false, 'http_errors' => false,
		])->willReturn($http);
		$client = new GitLabReportClient('synthetic-gitlab-token', $factory, new Psr7RequestFactory());
		$reference = new GitLabReference('company/team/project', 'merge_requests', 12, 77);
		self::assertSame([
			'title' => 'Fix login', 'state' => 'opened', 'description' => 'Details', 'updatedAt' => '2026-10-26T14:00:00Z',
		], $client->getItem($reference));
		$note = $client->getNote($reference);
		self::assertSame('Verified fix', $note['body']);
		self::assertSame('Developer', $note['author']);
		self::assertStringNotContainsString('private@example.com', Json::encode($note));
	}

	public function testMissingTokenFailsBeforeAnyHttpCall(): void
	{
		$factory = $this->createMock(Psr18ClientFactory::class);
		$factory->expects(self::never())->method('getClient');
		$this->expectException(WorkReportException::class);
		$this->expectExceptionMessage('Configure parameters.workReport.gitlabAccessToken');
		(new GitLabReportClient(null, $factory, new Psr7RequestFactory()))->assertConfigured();
	}

	public function testTransportExceptionDoesNotRetainAuthenticatedRequest(): void
	{
		$http = $this->createStub(ClientInterface::class);
		$http->method('sendRequest')->willThrowException(
			new ConnectException('private transport data', new Request('GET', 'https://gitlab.com')),
		);
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($http);
		try {
			(new GitLabReportClient('synthetic', $factory, new Psr7RequestFactory()))
				->getItem(new GitLabReference('company/project', 'issues', 1));
			self::fail('Expected a transport failure.');
		} catch (WorkReportException $exception) {
			self::assertSame('GitLab request failed.', $exception->getMessage());
			self::assertNull($exception->getPrevious());
		}
	}

	public function testInaccessibleItemDoesNotIncludeResponseBodyInError(): void
	{
		$http = $this->createStub(ClientInterface::class);
		$http->method('sendRequest')->willReturn(new Response(404, [], 'private response body'));
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($http);
		$this->expectExceptionMessage('GitLab returned HTTP 404.');
		(new GitLabReportClient('synthetic', $factory, new Psr7RequestFactory()))
			->getItem(new GitLabReference('company/project', 'issues', 1));
	}

}
