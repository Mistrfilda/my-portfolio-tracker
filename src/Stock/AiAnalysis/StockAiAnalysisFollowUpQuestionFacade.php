<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessMessage;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessProducer;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

class StockAiAnalysisFollowUpQuestionFacade
{

	public function __construct(
		private StockAiAnalysisRunRepository $stockAiAnalysisRunRepository,
		private StockAiAnalysisFollowUpQuestionRepository $stockAiAnalysisFollowUpQuestionRepository,
		private StockAiAnalysisFollowUpPromptGenerator $stockAiAnalysisFollowUpPromptGenerator,
		private StockAiAnalysisPromptGenerator $stockAiAnalysisPromptGenerator,
		private GeminiClient $geminiClient,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private StockAiAnalysisGeminiProcessProducer $stockAiAnalysisGeminiProcessProducer,
		private LoggerInterface $logger,
	)
	{
	}

	public function createQuestion(string $runId, string $question): StockAiAnalysisFollowUpQuestion
	{
		$question = TypeValidator::validateString($question);
		$run = $this->stockAiAnalysisRunRepository->getById(Uuid::fromString($runId));
		$generatedPrompt = $this->stockAiAnalysisFollowUpPromptGenerator->generate($run, $question);
		$followUpQuestion = new StockAiAnalysisFollowUpQuestion(
			$run,
			$question,
			$generatedPrompt,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($followUpQuestion);
		$this->entityManager->flush();

		return $followUpQuestion;
	}

	public function getQuestion(string $questionId): StockAiAnalysisFollowUpQuestion
	{
		return $this->stockAiAnalysisFollowUpQuestionRepository->getById(Uuid::fromString($questionId));
	}

	/**
	 * @return array<StockAiAnalysisFollowUpQuestion>
	 */
	public function getQuestionsForRun(StockAiAnalysisRun $run): array
	{
		return $this->stockAiAnalysisFollowUpQuestionRepository->findByRun($run);
	}

	public function processManualResponse(string $questionId, string $rawResponse): void
	{
		$rawResponse = TypeValidator::validateString($rawResponse);
		$followUpQuestion = $this->getQuestion($questionId);
		$followUpQuestion->setResponse($rawResponse, $this->datetimeFactory->createNow());

		$this->entityManager->flush();
	}

	public function enqueueGeminiProcessing(string $questionId): void
	{
		$followUpQuestion = $this->getQuestion($questionId);
		if (!$followUpQuestion->canBeQueuedForGeminiProcessing()) {
			return;
		}

		$now = $this->datetimeFactory->createNow();
		$followUpQuestion->markGeminiQueued($now);
		$this->entityManager->flush();

		$this->stockAiAnalysisGeminiProcessProducer->publish(new StockAiAnalysisGeminiProcessMessage(
			Uuid::uuid4()->toString(),
			$now->getTimestamp(),
			$followUpQuestion->getStockAiAnalysisRun()->getId()->toString(),
			StockAiAnalysisGeminiProcessMessage::TARGET_FOLLOW_UP,
			$questionId,
		));
	}

	public function processGeminiQuestion(string $questionId): void
	{
		$followUpQuestion = $this->getQuestion($questionId);
		if ($followUpQuestion->getRawResponse() !== null) {
			return;
		}

		$followUpQuestion->markGeminiProcessing($this->datetimeFactory->createNow());
		$this->entityManager->flush();

		try {
			$response = $this->geminiClient->generateContent(
				$followUpQuestion->getGeneratedPrompt(),
				$this->stockAiAnalysisPromptGenerator->generateSystemInstruction(),
				null,
			);
			$followUpQuestion->setResponse(
				$this->normalizeGeminiResponse($response),
				$this->datetimeFactory->createNow(),
			);
			$followUpQuestion->markGeminiCompleted($this->datetimeFactory->createNow());
			$this->entityManager->flush();
		} catch (Throwable $exception) {
			$followUpQuestion->markGeminiFailed($this->datetimeFactory->createNow(), $exception->getMessage());
			$this->entityManager->flush();
			$this->logger->error('Gemini stock AI analysis follow-up processing failed', [
				'questionId' => $questionId,
				'exception' => $exception,
			]);

			throw $exception;
		}
	}

	private function normalizeGeminiResponse(string $response): string
	{
		$trimmedResponse = trim($response);
		if (str_starts_with($trimmedResponse, '```')) {
			$trimmedResponse = preg_replace(
				'/^```(?:json)?\s*|\s*```$/',
				'',
				$trimmedResponse,
			) ?? $trimmedResponse;
			$trimmedResponse = trim($trimmedResponse);
		}

		if (!str_starts_with($trimmedResponse, '{') || !str_ends_with($trimmedResponse, '}')) {
			return $response;
		}

		try {
			$data = Json::decode($trimmedResponse, forceArrays: true);
		} catch (JsonException) {
			return $response;
		}

		if (!is_array($data) || !array_key_exists('response', $data) || !is_string($data['response'])) {
			return $response;
		}

		return TypeValidator::validateString($data['response']);
	}

}
