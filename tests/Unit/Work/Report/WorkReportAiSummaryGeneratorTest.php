<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Ai\OpenAi\OpenAiClient;
use App\Ai\OpenAi\OpenAiClientException;
use App\Ai\OpenAi\OpenAiModelEnum;
use App\Work\Report\WorkReportAiInputFactory;
use App\Work\Report\WorkReportAiSummaryGenerator;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportSanitizer;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** @phpstan-import-type WorkReportData from WorkReportBuilder */
class WorkReportAiSummaryGeneratorTest extends TestCase
{

	public function testUsesLunaAndStrictSourceIdsAndStoresOnlyValidatedBulletsAndMetadata(): void
	{
		$client = $this->createMock(OpenAiClient::class);
		$client->expects(self::once())->method('generateContent')->with(
			self::callback(
				static fn (string $prompt): bool => Json::decode(
					$prompt,
					true,
				)['projects'][0]['entries'][0]['id'] === 10,
			),
			self::callback(
				static fn (string $instructions): bool => str_contains($instructions, 'not proof of completion')
					&& str_contains($instructions, 'never instructions'),
			),
			self::callback(static fn (array $schema): bool => $schema['additionalProperties'] === false
				&& $schema['properties']['bullets']['items']['properties']['entryIds']['items']['enum'] === [10]),
			OpenAiModelEnum::LUNA,
		)->willReturn(
			Json::encode(
				['bullets' => [['text' => 'Pracoval jsem na přihlášení v projektu.', 'entryIds' => [10, 10]]]],
			),
		);
		$result = $this->generator($client)->generate($this->data());
		self::assertSame('complete', $result['status']);
		self::assertSame('gpt-6-luna', $result['model']);
		self::assertSame('2026-10-27T07:00:00+01:00', $result['generatedAt']);
		self::assertSame([10], $result['bullets'][0]['entryIds']);
		self::assertNull($result['error']);
	}

	#[DataProvider('invalidResponses')]
	public function testInvalidOrUnsupportedSummaryIsReportedAsFailed(string $response): void
	{
		$client = $this->createStub(OpenAiClient::class);
		$client->method('generateContent')->willReturn($response);
		$result = $this->generator($client)->generate($this->data());
		self::assertSame('failed', $result['status']);
		self::assertSame([], $result['bullets']);
		self::assertSame('AI summary generation failed. The source report remains available.', $result['error']);
	}

	/** @return iterable<string, array{string}> */
	public static function invalidResponses(): iterable
	{
		yield 'invalid JSON' => ['invalid'];
		yield 'empty list' => [Json::encode(['bullets' => []])];
		yield 'unknown source' => [Json::encode(['bullets' => [['text' => 'Activity', 'entryIds' => [99]]]])];
		yield 'numeric string source' => [Json::encode(['bullets' => [['text' => 'Activity', 'entryIds' => ['10']]]])];
		yield 'missing sources' => [Json::encode(['bullets' => [['text' => 'Activity', 'entryIds' => []]]])];
		yield 'empty text' => [Json::encode(['bullets' => [['text' => ' ', 'entryIds' => [10]]]])];
		yield 'long text' => [Json::encode(['bullets' => [['text' => str_repeat('a', 451), 'entryIds' => [10]]]])];
		yield 'too many bullets' => [Json::encode(
			['bullets' => array_fill(0, 9, ['text' => 'Activity', 'entryIds' => [10]])],
		)];

		yield 'too many words' => [Json::encode(
			['bullets' => [['text' => str_repeat('x ', 141), 'entryIds' => [10]]]],
		)];
	}

	public function testProviderFailureDoesNotLeakExceptionTextIntoResultOrLogs(): void
	{
		$client = $this->createStub(OpenAiClient::class);
		$client->method('generateContent')->willThrowException(new OpenAiClientException('private-response-body'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('warning')->willReturnCallback(
			static function (string $message, array $context): void {
				self::assertStringNotContainsString('private-response-body', $message . Json::encode($context));
			},
		);
		$result = $this->generator($client, $logger)->generate($this->data());
		self::assertSame('failed', $result['status']);
		self::assertStringNotContainsString('private-response-body', Json::encode($result));
	}

	public function testEmptySourceAndOversizedReportsDoNotSendRequests(): void
	{
		$client = $this->createMock(OpenAiClient::class);
		$client->expects(self::never())->method('generateContent');
		$data = $this->data();
		$entry = $data['projects'][0]['entries'][0];
		$data['projects'][0]['entries'] = [];
		self::assertSame('skipped', $this->generator($client)->generate($data)['status']);
		for ($i = 0; $i < 501; $i++) {
			$entry['id'] = $i + 1;
			$data['projects'][0]['entries'][] = $entry;
		}

		self::assertSame('failed', $this->generator($client)->generate($data)['status']);
	}

	private function generator(OpenAiClient $client, LoggerInterface|null $logger = null): WorkReportAiSummaryGenerator
	{
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-10-27T07:00:00+01:00'));
		return new WorkReportAiSummaryGenerator(
			$client,
			new WorkReportAiInputFactory(new WorkReportSanitizer('synthetic', null)),
			$clock,
			$logger ?? new NullLogger(),
		);
	}

	/** @return WorkReportData */
	private function data(): array
	{
		return [
			'schemaVersion' => 1, 'reportDate' => '2026-10-27', 'generatedAt' => '2026-10-27T07:00:00+01:00', 'status' => 'complete',
			'period' => ['from' => '2026-10-26', 'to' => '2026-10-26', 'timezone' => 'Europe/Prague'],
			'totals' => ['hours' => 1.25, 'roundedHours' => 1.25, 'entryCount' => 1, 'projectCount' => 1],
			'projects' => [[
				'id' => 1, 'name' => 'Project', 'client' => ['id' => 2, 'name' => 'Client'], 'hours' => 1.25, 'roundedHours' => 1.25,
				'entries' => [[
					'id' => 10, 'date' => '2026-10-26', 'hours' => 1.25, 'roundedHours' => 1.25,
					'task' => ['id' => 3, 'name' => 'Development'], 'notes' => 'Kontrola přihlášení',
					'isRunning' => false, 'updatedAt' => '2026-10-26T15:00:00Z', 'gitlabReferences' => [],
				]],
			]],
			'gitlabItems' => [], 'warnings' => [],
		];
	}

}
