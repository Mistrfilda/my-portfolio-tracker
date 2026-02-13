<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_ai_analysis_run')]
class StockAiAnalysisRun implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::TEXT)]
	private string $generatedPrompt;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $rawResponse = null;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $includesPortfolio;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $includesWatchlist;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $includesMarketOverview;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $marketOverviewSummary = null;

	#[ORM\Column(type: Types::STRING, nullable: true, enumType: StockAiAnalysisMarketSentimentEnum::class)]
	private StockAiAnalysisMarketSentimentEnum|null $marketOverviewSentiment = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processedAt = null;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stockTicker = null;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stockName = null;

	/** @var Collection<int, StockAiAnalysisStockResult> */
	#[ORM\OneToMany(
		targetEntity: StockAiAnalysisStockResult::class,
		mappedBy: 'stockAiAnalysisRun',
		cascade: ['persist', 'remove'],
	)]
	private Collection $results;

	public function __construct(
		string $generatedPrompt,
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		ImmutableDateTime $now,
		string|null $stockTicker = null,
		string|null $stockName = null,
	)
	{
		$this->id = Uuid::uuid4();
		$this->generatedPrompt = $generatedPrompt;
		$this->includesPortfolio = $includesPortfolio;
		$this->includesWatchlist = $includesWatchlist;
		$this->includesMarketOverview = $includesMarketOverview;
		$this->stockTicker = $stockTicker;
		$this->stockName = $stockName;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->results = new ArrayCollection();
	}

	public function setResponse(
		string $rawResponse,
		string|null $marketOverviewSummary,
		StockAiAnalysisMarketSentimentEnum|null $marketOverviewSentiment,
		ImmutableDateTime $now,
	): void
	{
		$this->rawResponse = $rawResponse;
		$this->marketOverviewSummary = $marketOverviewSummary;
		$this->marketOverviewSentiment = $marketOverviewSentiment;
		$this->processedAt = $now;
		$this->updatedAt = $now;
	}

	public function getGeneratedPrompt(): string
	{
		return $this->generatedPrompt;
	}

	public function getRawResponse(): string|null
	{
		return $this->rawResponse;
	}

	public function includesPortfolio(): bool
	{
		return $this->includesPortfolio;
	}

	public function includesWatchlist(): bool
	{
		return $this->includesWatchlist;
	}

	public function includesMarketOverview(): bool
	{
		return $this->includesMarketOverview;
	}

	public function getMarketOverviewSummary(): string|null
	{
		return $this->marketOverviewSummary;
	}

	public function getMarketOverviewSentiment(): StockAiAnalysisMarketSentimentEnum|null
	{
		return $this->marketOverviewSentiment;
	}

	public function getProcessedAt(): ImmutableDateTime|null
	{
		return $this->processedAt;
	}

	/**
	 * @return Collection<int, StockAiAnalysisStockResult>
	 */
	public function getResults(): Collection
	{
		return $this->results;
	}

	public function getStockTicker(): string|null
	{
		return $this->stockTicker;
	}

	public function getStockName(): string|null
	{
		return $this->stockName;
	}

	public function addResult(StockAiAnalysisStockResult $result): void
	{
		$this->results->add($result);
	}

}
