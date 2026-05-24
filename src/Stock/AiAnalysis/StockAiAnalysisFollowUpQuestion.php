<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_ai_analysis_follow_up_question')]
class StockAiAnalysisFollowUpQuestion implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAiAnalysisRun::class)]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private StockAiAnalysisRun $stockAiAnalysisRun;

	#[ORM\Column(type: Types::TEXT)]
	private string $question;

	#[ORM\Column(type: Types::TEXT)]
	private string $generatedPrompt;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $rawResponse = null;

	#[ORM\Column(type: Types::STRING, nullable: true, enumType: StockAiAnalysisFollowUpStatusEnum::class)]
	private StockAiAnalysisFollowUpStatusEnum|null $geminiProcessingStatus = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $geminiProcessingQueuedAt = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $geminiProcessingStartedAt = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $geminiProcessingFinishedAt = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $geminiProcessingError = null;

	public function __construct(
		StockAiAnalysisRun $stockAiAnalysisRun,
		string $question,
		string $generatedPrompt,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->stockAiAnalysisRun = $stockAiAnalysisRun;
		$this->question = $question;
		$this->generatedPrompt = $generatedPrompt;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function setResponse(string $rawResponse, ImmutableDateTime $now): void
	{
		$this->rawResponse = $rawResponse;
		$this->updatedAt = $now;
	}

	public function markGeminiQueued(ImmutableDateTime $now): void
	{
		$this->geminiProcessingStatus = StockAiAnalysisFollowUpStatusEnum::QUEUED;
		$this->geminiProcessingQueuedAt = $now;
		$this->geminiProcessingError = null;
		$this->updatedAt = $now;
	}

	public function markGeminiProcessing(ImmutableDateTime $now): void
	{
		$this->geminiProcessingStatus = StockAiAnalysisFollowUpStatusEnum::PROCESSING;
		$this->geminiProcessingStartedAt = $now;
		$this->geminiProcessingError = null;
		$this->updatedAt = $now;
	}

	public function markGeminiCompleted(ImmutableDateTime $now): void
	{
		$this->geminiProcessingStatus = StockAiAnalysisFollowUpStatusEnum::COMPLETED;
		$this->geminiProcessingFinishedAt = $now;
		$this->geminiProcessingError = null;
		$this->updatedAt = $now;
	}

	public function markGeminiFailed(ImmutableDateTime $now, string $error): void
	{
		$this->geminiProcessingStatus = StockAiAnalysisFollowUpStatusEnum::FAILED;
		$this->geminiProcessingFinishedAt = $now;
		$this->geminiProcessingError = $error;
		$this->updatedAt = $now;
	}

	public function getStockAiAnalysisRun(): StockAiAnalysisRun
	{
		return $this->stockAiAnalysisRun;
	}

	public function getQuestion(): string
	{
		return $this->question;
	}

	public function getGeneratedPrompt(): string
	{
		return $this->generatedPrompt;
	}

	public function getRawResponse(): string|null
	{
		return $this->rawResponse;
	}

	public function getGeminiProcessingStatus(): StockAiAnalysisFollowUpStatusEnum|null
	{
		return $this->geminiProcessingStatus;
	}

	public function getGeminiProcessingQueuedAt(): ImmutableDateTime|null
	{
		return $this->geminiProcessingQueuedAt;
	}

	public function getGeminiProcessingStartedAt(): ImmutableDateTime|null
	{
		return $this->geminiProcessingStartedAt;
	}

	public function getGeminiProcessingFinishedAt(): ImmutableDateTime|null
	{
		return $this->geminiProcessingFinishedAt;
	}

	public function getGeminiProcessingError(): string|null
	{
		return $this->geminiProcessingError;
	}

	public function canBeQueuedForGeminiProcessing(): bool
	{
		return $this->rawResponse === null
			&& !in_array(
				$this->geminiProcessingStatus,
				[StockAiAnalysisFollowUpStatusEnum::QUEUED, StockAiAnalysisFollowUpStatusEnum::PROCESSING],
				true,
			);
	}

}
