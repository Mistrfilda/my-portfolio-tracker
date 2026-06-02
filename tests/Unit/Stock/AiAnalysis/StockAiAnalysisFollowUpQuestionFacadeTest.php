<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\RabbitMQ\RabbitMQPublisher;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessMessage;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessProducer;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestion;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestionFacade;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestionRepository;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpStatusEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisFollowUpQuestionFacadeTest extends TestCase
{

	public function testCreatesQuestionWithGeneratedPrompt(): void
	{
		$now = new ImmutableDateTime('2026-05-24 10:00:00');
		$runId = Uuid::uuid4();
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $now);
		$runRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisRunRepository::class);
		$questionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisFollowUpQuestionRepository::class,
		);
		$promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisFollowUpPromptGenerator::class);
		$entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$runRepository->shouldReceive('getById')
			->withArgs(static fn ($id): bool => $id->equals($runId))
			->once()
			->andReturn($run);
		$promptGenerator->shouldReceive('generate')
			->with($run, 'What changed?')
			->once()
			->andReturn('Generated follow-up prompt');
		$datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);
		$entityManager->shouldReceive('persist')
			->once()
			->withArgs(
				static fn (StockAiAnalysisFollowUpQuestion $question): bool => $question->getQuestion() === 'What changed?'
					&& $question->getGeneratedPrompt() === 'Generated follow-up prompt',
			);
		$entityManager->shouldReceive('flush')
			->once();

		$question = $this->createFacade(
			$runRepository,
			$questionRepository,
			$promptGenerator,
			$entityManager,
			$datetimeFactory,
		)->createQuestion($runId->toString(), 'What changed?');

		self::assertSame($run, $question->getStockAiAnalysisRun());
		self::assertSame('What changed?', $question->getQuestion());
		self::assertSame('Generated follow-up prompt', $question->getGeneratedPrompt());
	}

	public function testStoresManualResponse(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$responseAt = new ImmutableDateTime('2026-05-24 11:00:00');
		$questionId = Uuid::uuid4();
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Question', 'Generated prompt', $createdAt);
		$runRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisRunRepository::class);
		$questionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisFollowUpQuestionRepository::class,
		);
		$promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisFollowUpPromptGenerator::class);
		$entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$questionRepository->shouldReceive('getById')
			->withArgs(static fn ($id): bool => $id->equals($questionId))
			->once()
			->andReturn($question);
		$datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($responseAt);
		$entityManager->shouldReceive('flush')
			->once();

		$this->createFacade(
			$runRepository,
			$questionRepository,
			$promptGenerator,
			$entityManager,
			$datetimeFactory,
		)->processManualResponse($questionId->toString(), 'Manual answer');

		self::assertSame('Manual answer', $question->getRawResponse());
		self::assertSame($responseAt, $question->getUpdatedAt());
		self::assertNull($run->getRawResponse());
	}

	public function testEnqueuesGeminiProcessingForFollowUpQuestion(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$queuedAt = new ImmutableDateTime('2026-05-24 11:00:00');
		$questionId = Uuid::uuid4();
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Question', 'Generated prompt', $createdAt);
		$publisher = new class implements RabbitMQPublisher {

			public string|null $payload = null;

			/**
			 * @param array<string, mixed> $headers
			 */
			public function publish(string $queueName, string $payload, array $headers = []): void
			{
				$this->payload = $payload;
			}

		};
		$runRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisRunRepository::class);
		$questionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisFollowUpQuestionRepository::class,
		);
		$promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisFollowUpPromptGenerator::class);
		$entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$questionRepository->shouldReceive('getById')
			->withArgs(static fn ($id): bool => $id->equals($questionId))
			->once()
			->andReturn($question);
		$datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($queuedAt);
		$entityManager->shouldReceive('flush')
			->once();

		$this->createFacade(
			$runRepository,
			$questionRepository,
			$promptGenerator,
			$entityManager,
			$datetimeFactory,
			new StockAiAnalysisGeminiProcessProducer($publisher, 'aiClientsQueue'),
		)->enqueueGeminiProcessing($questionId->toString());

		$payload = Json::decode($publisher->payload, forceArrays: true);
		self::assertSame(StockAiAnalysisFollowUpStatusEnum::QUEUED, $question->getGeminiProcessingStatus());
		self::assertSame(StockAiAnalysisGeminiProcessMessage::TARGET_FOLLOW_UP, $payload['targetType']);
		self::assertSame($questionId->toString(), $payload['followUpQuestionId']);
		self::assertSame($run->getId()->toString(), $payload['runId']);
	}

	public function testProcessesGeminiQuestion(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$processingAt = new ImmutableDateTime('2026-05-24 11:00:00');
		$responseAt = new ImmutableDateTime('2026-05-24 11:01:00');
		$completedAt = new ImmutableDateTime('2026-05-24 11:02:00');
		$questionId = Uuid::uuid4();
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Question', 'Generated prompt', $createdAt);
		$runRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisRunRepository::class);
		$questionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisFollowUpQuestionRepository::class,
		);
		$promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisFollowUpPromptGenerator::class);
		$stockAiAnalysisPromptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisPromptGenerator::class,
		);
		$geminiClient = UpdatedTestCase::createMockWithIgnoreMethods(GeminiClient::class);
		$entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$questionRepository->shouldReceive('getById')
			->withArgs(static fn ($id): bool => $id->equals($questionId))
			->once()
			->andReturn($question);
		$datetimeFactory->shouldReceive('createNow')
			->times(3)
			->andReturn($processingAt, $responseAt, $completedAt);
		$entityManager->shouldReceive('flush')
			->twice();
		$stockAiAnalysisPromptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('System instruction');
		$geminiClient->shouldReceive('generateContent')
			->with('Generated prompt', 'System instruction', null)
			->once()
			->andReturn('Gemini answer');

		$this->createFacade(
			$runRepository,
			$questionRepository,
			$promptGenerator,
			$entityManager,
			$datetimeFactory,
			stockAiAnalysisPromptGenerator: $stockAiAnalysisPromptGenerator,
			geminiClient: $geminiClient,
		)->processGeminiQuestion($questionId->toString());

		self::assertSame('Gemini answer', $question->getRawResponse());
		self::assertSame(StockAiAnalysisFollowUpStatusEnum::COMPLETED, $question->getGeminiProcessingStatus());
		self::assertSame($completedAt, $question->getGeminiProcessingFinishedAt());
	}

	public function testProcessesGeminiQuestionWithJsonResponseWrapper(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$processingAt = new ImmutableDateTime('2026-05-24 11:00:00');
		$responseAt = new ImmutableDateTime('2026-05-24 11:01:00');
		$completedAt = new ImmutableDateTime('2026-05-24 11:02:00');
		$questionId = Uuid::uuid4();
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Question', 'Generated prompt', $createdAt);
		$runRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisRunRepository::class);
		$questionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisFollowUpQuestionRepository::class,
		);
		$promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisFollowUpPromptGenerator::class);
		$stockAiAnalysisPromptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisPromptGenerator::class,
		);
		$geminiClient = UpdatedTestCase::createMockWithIgnoreMethods(GeminiClient::class);
		$entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$questionRepository->shouldReceive('getById')
			->withArgs(static fn ($id): bool => $id->equals($questionId))
			->once()
			->andReturn($question);
		$datetimeFactory->shouldReceive('createNow')
			->times(3)
			->andReturn($processingAt, $responseAt, $completedAt);
		$entityManager->shouldReceive('flush')
			->twice();
		$stockAiAnalysisPromptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('System instruction');
		$geminiClient->shouldReceive('generateContent')
			->with('Generated prompt', 'System instruction', null)
			->once()
			->andReturn(Json::encode(['response' => "Gemini answer\nwith second line"]));

		$this->createFacade(
			$runRepository,
			$questionRepository,
			$promptGenerator,
			$entityManager,
			$datetimeFactory,
			stockAiAnalysisPromptGenerator: $stockAiAnalysisPromptGenerator,
			geminiClient: $geminiClient,
		)->processGeminiQuestion($questionId->toString());

		self::assertSame("Gemini answer\nwith second line", $question->getRawResponse());
		self::assertSame(StockAiAnalysisFollowUpStatusEnum::COMPLETED, $question->getGeminiProcessingStatus());
		self::assertSame($completedAt, $question->getGeminiProcessingFinishedAt());
	}

	private function createFacade(
		StockAiAnalysisRunRepository $runRepository,
		StockAiAnalysisFollowUpQuestionRepository $questionRepository,
		StockAiAnalysisFollowUpPromptGenerator $promptGenerator,
		EntityManagerInterface $entityManager,
		DatetimeFactory $datetimeFactory,
		StockAiAnalysisGeminiProcessProducer|null $producer = null,
		StockAiAnalysisPromptGenerator|null $stockAiAnalysisPromptGenerator = null,
		GeminiClient|null $geminiClient = null,
		LoggerInterface|null $logger = null,
	): StockAiAnalysisFollowUpQuestionFacade
	{
		return new StockAiAnalysisFollowUpQuestionFacade(
			$runRepository,
			$questionRepository,
			$promptGenerator,
			$stockAiAnalysisPromptGenerator ?? UpdatedTestCase::createMockWithIgnoreMethods(
				StockAiAnalysisPromptGenerator::class,
			),
			$geminiClient ?? UpdatedTestCase::createMockWithIgnoreMethods(GeminiClient::class),
			$entityManager,
			$datetimeFactory,
			$producer ?? new StockAiAnalysisGeminiProcessProducer(
				$this->createStub(RabbitMQPublisher::class),
				'aiClientsQueue',
			),
			$logger ?? UpdatedTestCase::createMockWithIgnoreMethods(LoggerInterface::class),
		);
	}

}
