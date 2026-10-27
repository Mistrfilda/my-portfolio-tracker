<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Harvest;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Work\Harvest\HarvestReportClient;
use App\Work\Report\WorkReportException;
use App\Work\Report\WorkReportPeriod;
use Nette\Utils\Json;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

class HarvestReportClientTest extends TestCase
{

	public function testFetchesAllPagesForOnlyConfiguredUserAndPeriodAndSelectsReportFields(): void
	{
		$http = $this->createMock(ClientInterface::class);
		$page = 0;
		$http->expects(self::exactly(2))->method('sendRequest')->willReturnCallback(
			static function (RequestInterface $request) use (&$page): Response {
				$page++;
				self::assertSame('GET', $request->getMethod());
				self::assertSame('api.harvestapp.com', $request->getUri()->getHost());
				self::assertSame('Bearer synthetic-harvest-token', $request->getHeaderLine('Authorization'));
				self::assertSame('10', $request->getHeaderLine('Harvest-Account-Id'));
				parse_str($request->getUri()->getQuery(), $query);
				self::assertSame([
					'user_id' => '20', 'from' => '2026-10-26', 'to' => '2026-10-26', 'page' => (string) $page, 'per_page' => '2000',
				], $query);
				return new Response(200, [], Json::encode([
					'time_entries' => [self::entry($page)],
					'next_page' => $page === 1 ? 2 : null,
				]));
			},
		);
		$result = $this->client($http)->getEntries(WorkReportPeriod::create('2026-10-27'));
		self::assertCount(2, $result);
		self::assertSame('Fixed login', $result[0]['notes']);
		self::assertSame(1.1, $result[0]['hours']);
		self::assertSame(1.25, $result[0]['roundedHours']);
		self::assertArrayNotHasKey('billable_rate', $result[0]);
		self::assertArrayNotHasKey('cost_rate', $result[0]);
	}

	#[DataProvider('httpFailures')]
	public function testHttpFailureIsNotAnEmptyDayAndDoesNotExposeResponse(int $status): void
	{
		$http = $this->createStub(ClientInterface::class);
		$http->method('sendRequest')->willReturn(new Response($status, [], 'private server response'));
		$this->expectException(WorkReportException::class);
		$this->expectExceptionMessage('Harvest returned HTTP ' . $status . '.');
		$this->client($http)->getEntries(WorkReportPeriod::create('2026-10-27'));
	}

	/** @return iterable<array{int}> */
	public static function httpFailures(): iterable
	{
		yield [302];
		yield [401];
		yield [429];
		yield [500];
	}

	#[DataProvider('invalidResponses')]
	public function testRejectsMalformedOrUnexpectedEntries(string $body): void
	{
		$http = $this->createStub(ClientInterface::class);
		$http->method('sendRequest')->willReturn(new Response(200, [], $body));
		$this->expectException(WorkReportException::class);
		$this->client($http)->getEntries(WorkReportPeriod::create('2026-10-27'));
	}

	/** @return iterable<string, array{string}> */
	public static function invalidResponses(): iterable
	{
		yield 'invalid JSON' => ['not-json'];
		yield 'missing entries' => [Json::encode(['next_page' => null])];
		yield 'missing pagination' => [Json::encode(['time_entries' => []])];

		foreach ([
			'user' => ['id' => 999], 'spent_date' => '2026-10-25', 'hours' => -1, 'project' => null,
		] as $field => $value) {
			yield $field => [Json::encode(
				['time_entries' => [array_replace(self::entry(1), [$field => $value])], 'next_page' => null],
			)];
		}

		yield 'duplicate entries' => [Json::encode(
			['time_entries' => [self::entry(1), self::entry(1)], 'next_page' => null],
		)];
	}

	public function testPaginationCycleFailsInsteadOfSavingAnIncompleteReport(): void
	{
		$http = $this->createMock(ClientInterface::class);
		$http->expects(self::once())->method('sendRequest')->willReturn(
			new Response(200, [], Json::encode(['time_entries' => [], 'next_page' => 1])),
		);
		$this->expectExceptionMessage('Harvest pagination did not finish.');
		$this->client($http)->getEntries(WorkReportPeriod::create('2026-10-27'));
	}

	public function testSuccessfulEmptyDayReturnsEmptyList(): void
	{
		$http = $this->createStub(ClientInterface::class);
		$http->method('sendRequest')->willReturn(
			new Response(200, [], Json::encode(['time_entries' => [], 'next_page' => null])),
		);
		self::assertSame([], $this->client($http)->getEntries(WorkReportPeriod::create('2026-10-27')));
	}

	/** @return array<string, mixed> */
	private static function entry(int $id): array
	{
		return [
			'id' => $id, 'spent_date' => '2026-10-26', 'user' => ['id' => 20],
			'hours' => 1.1, 'rounded_hours' => 1.25, 'notes' => 'Fixed login',
			'project' => ['id' => 30, 'name' => 'Project'], 'client' => ['id' => 40, 'name' => 'Client'],
			'task' => ['id' => 50, 'name' => 'Development'], 'is_running' => false,
			'updated_at' => '2026-10-26T15:00:00Z', 'external_reference' => null,
			'billable_rate' => 1000, 'cost_rate' => 500,
		];
	}

	private function client(ClientInterface $http): HarvestReportClient
	{
		$factory = $this->createStub(Psr18ClientFactory::class);
		$factory->method('getClient')->willReturn($http);
		return new HarvestReportClient(
			10,
			20,
			'synthetic-harvest-token',
			$factory,
			new Psr7RequestFactory(),
			new NullLogger(),
		);
	}

}
