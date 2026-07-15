<?php

declare(strict_types = 1);

namespace App\Test\Unit\JobRequest\RabbitMQ;

use App\JobRequest\JobRequestProcessor;
use App\JobRequest\JobRequestTypeEnum;
use App\JobRequest\RabbitMQ\JobRequestConsumer;
use App\RabbitMQ\RabbitMQConsumeResult;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracy\Debugger;
use Tracy\ILogger;

class JobRequestConsumerTest extends TestCase
{

	private ILogger $originalLogger;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalLogger = Debugger::getLogger();
		Debugger::setLogger(new class implements ILogger {

			public function log(mixed $value, string $level = self::INFO): void
			{
				// Prevent unit tests from writing consumer messages to the filesystem.
			}

		});
	}

	protected function tearDown(): void
	{
		Debugger::setLogger($this->originalLogger);

		parent::tearDown();
	}

	public function testClearsEntityManagerBeforeAndAfterProcessingMessage(): void
	{
		$processor = $this->createMock(JobRequestProcessor::class);
		$processor->expects(self::once())
			->method('process')
			->with(
				JobRequestTypeEnum::PORTFOLIO_PERIOD_STATISTIC_PROCESS,
				['reportId' => 'report-id'],
			);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::exactly(2))->method('clear');

		$result = (new JobRequestConsumer($processor, $entityManager))->consume($this->createPayload());

		self::assertSame(RabbitMQConsumeResult::Ack, $result);
	}

	public function testClearsEntityManagerWhenProcessingFails(): void
	{
		$exception = new RuntimeException('Processing failed');
		$processor = $this->createMock(JobRequestProcessor::class);
		$processor->expects(self::once())->method('process')->willThrowException($exception);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::exactly(2))->method('clear');

		$this->expectExceptionObject($exception);

		(new JobRequestConsumer($processor, $entityManager))->consume($this->createPayload());
	}

	private function createPayload(): string
	{
		return Json::encode([
			'requestId' => 'request-id',
			'messageQueuedAtTimestamp' => 1_789_200_000,
			'jobRequestType' => JobRequestTypeEnum::PORTFOLIO_PERIOD_STATISTIC_PROCESS->value,
			'additionalData' => ['reportId' => 'report-id'],
		]);
	}

}
