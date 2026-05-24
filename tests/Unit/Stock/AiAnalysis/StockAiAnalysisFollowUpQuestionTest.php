<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestion;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpStatusEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisFollowUpQuestionTest extends TestCase
{

	public function testStoresQuestionPromptAndManualResponse(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$responseAt = new ImmutableDateTime('2026-05-24 11:00:00');
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);

		$question = new StockAiAnalysisFollowUpQuestion(
			$run,
			'What changed since the original analysis?',
			'Generated follow-up prompt',
			$createdAt,
		);
		$question->setResponse('Manual answer', $responseAt);

		self::assertSame($run, $question->getStockAiAnalysisRun());
		self::assertSame('What changed since the original analysis?', $question->getQuestion());
		self::assertSame('Generated follow-up prompt', $question->getGeneratedPrompt());
		self::assertSame('Manual answer', $question->getRawResponse());
		self::assertSame($createdAt, $question->getCreatedAt());
		self::assertSame($responseAt, $question->getUpdatedAt());
		self::assertFalse($question->canBeQueuedForGeminiProcessing());
	}

	public function testGeminiStatusTransitions(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$queuedAt = new ImmutableDateTime('2026-05-24 10:01:00');
		$processingAt = new ImmutableDateTime('2026-05-24 10:02:00');
		$completedAt = new ImmutableDateTime('2026-05-24 10:03:00');
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Question', 'Generated prompt', $createdAt);

		self::assertTrue($question->canBeQueuedForGeminiProcessing());

		$question->markGeminiQueued($queuedAt);

		self::assertSame(StockAiAnalysisFollowUpStatusEnum::QUEUED, $question->getGeminiProcessingStatus());
		self::assertSame($queuedAt, $question->getGeminiProcessingQueuedAt());
		self::assertNull($question->getGeminiProcessingError());
		self::assertFalse($question->canBeQueuedForGeminiProcessing());

		$question->markGeminiProcessing($processingAt);

		self::assertSame(StockAiAnalysisFollowUpStatusEnum::PROCESSING, $question->getGeminiProcessingStatus());
		self::assertSame($processingAt, $question->getGeminiProcessingStartedAt());
		self::assertNull($question->getGeminiProcessingError());
		self::assertFalse($question->canBeQueuedForGeminiProcessing());

		$question->markGeminiCompleted($completedAt);

		self::assertSame(StockAiAnalysisFollowUpStatusEnum::COMPLETED, $question->getGeminiProcessingStatus());
		self::assertSame($completedAt, $question->getGeminiProcessingFinishedAt());
		self::assertNull($question->getGeminiProcessingError());
		self::assertTrue($question->canBeQueuedForGeminiProcessing());
	}

	public function testGeminiFailureCanBeRetried(): void
	{
		$createdAt = new ImmutableDateTime('2026-05-24 10:00:00');
		$failedAt = new ImmutableDateTime('2026-05-24 10:03:00');
		$run = new StockAiAnalysisRun('Original prompt', true, false, false, null, $createdAt);
		$question = new StockAiAnalysisFollowUpQuestion($run, 'Question', 'Generated prompt', $createdAt);

		$question->markGeminiFailed($failedAt, 'Gemini request failed');

		self::assertSame(StockAiAnalysisFollowUpStatusEnum::FAILED, $question->getGeminiProcessingStatus());
		self::assertSame($failedAt, $question->getGeminiProcessingFinishedAt());
		self::assertSame('Gemini request failed', $question->getGeminiProcessingError());
		self::assertTrue($question->canBeQueuedForGeminiProcessing());
	}

}
