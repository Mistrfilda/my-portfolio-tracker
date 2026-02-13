<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_ai_analysis_stock_result')]
class StockAiAnalysisStockResult implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAiAnalysisRun::class, inversedBy: 'results')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAiAnalysisRun $stockAiAnalysisRun;

	#[ORM\ManyToOne(targetEntity: StockAsset::class)]
	#[ORM\JoinColumn(nullable: true)]
	private StockAsset|null $stockAsset;

	#[ORM\Column(type: Types::STRING, enumType: StockAiAnalysisResultTypeEnum::class)]
	private StockAiAnalysisResultTypeEnum $type;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stockTicker = null;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stockName = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $positiveNews = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $negativeNews = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $interestingNews = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $aiOpinion = null;

	#[ORM\Column(type: Types::STRING, nullable: true, enumType: StockAiAnalysisActionSuggestionEnum::class)]
	private StockAiAnalysisActionSuggestionEnum|null $actionSuggestion = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $reasoning = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $news = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $businessSummary = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $moatAnalysis = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $financialHealth = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $growthCatalysts = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $valuationAssessment = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $conclusion = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $risks = null;

	public function __construct(
		StockAiAnalysisRun $stockAiAnalysisRun,
		StockAsset|null $stockAsset,
		StockAiAnalysisResultTypeEnum $type,
		string|null $positiveNews,
		string|null $negativeNews,
		string|null $interestingNews,
		string|null $aiOpinion,
		StockAiAnalysisActionSuggestionEnum|null $actionSuggestion,
		string|null $reasoning,
		string|null $news,
		string|null $stockTicker,
		string|null $stockName,
		string|null $businessSummary,
		string|null $moatAnalysis,
		string|null $financialHealth,
		string|null $growthCatalysts,
		string|null $valuationAssessment,
		string|null $conclusion,
		string|null $risks,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->stockAiAnalysisRun = $stockAiAnalysisRun;
		$this->stockAsset = $stockAsset;
		$this->type = $type;
		$this->positiveNews = $positiveNews;
		$this->negativeNews = $negativeNews;
		$this->interestingNews = $interestingNews;
		$this->aiOpinion = $aiOpinion;
		$this->actionSuggestion = $actionSuggestion;
		$this->reasoning = $reasoning;
		$this->news = $news;
		$this->stockTicker = $stockTicker;
		$this->stockName = $stockName;
		$this->businessSummary = $businessSummary;
		$this->moatAnalysis = $moatAnalysis;
		$this->financialHealth = $financialHealth;
		$this->growthCatalysts = $growthCatalysts;
		$this->valuationAssessment = $valuationAssessment;
		$this->conclusion = $conclusion;
		$this->risks = $risks;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getStockAiAnalysisRun(): StockAiAnalysisRun
	{
		return $this->stockAiAnalysisRun;
	}

	public function getStockAsset(): StockAsset|null
	{
		return $this->stockAsset;
	}

	public function getType(): StockAiAnalysisResultTypeEnum
	{
		return $this->type;
	}

	public function getPositiveNews(): string|null
	{
		return $this->positiveNews;
	}

	public function getNegativeNews(): string|null
	{
		return $this->negativeNews;
	}

	public function getInterestingNews(): string|null
	{
		return $this->interestingNews;
	}

	public function getAiOpinion(): string|null
	{
		return $this->aiOpinion;
	}

	public function getActionSuggestion(): StockAiAnalysisActionSuggestionEnum|null
	{
		return $this->actionSuggestion;
	}

	public function getReasoning(): string|null
	{
		return $this->reasoning;
	}

	public function getNews(): string|null
	{
		return $this->news;
	}

	public function getStockTicker(): string|null
	{
		return $this->stockTicker;
	}

	public function getStockName(): string|null
	{
		return $this->stockName;
	}

	public function getBusinessSummary(): string|null
	{
		return $this->businessSummary;
	}

	public function getMoatAnalysis(): string|null
	{
		return $this->moatAnalysis;
	}

	public function getFinancialHealth(): string|null
	{
		return $this->financialHealth;
	}

	public function getGrowthCatalysts(): string|null
	{
		return $this->growthCatalysts;
	}

	public function getValuationAssessment(): string|null
	{
		return $this->valuationAssessment;
	}

	public function getConclusion(): string|null
	{
		return $this->conclusion;
	}

	public function getRisks(): string|null
	{
		return $this->risks;
	}

}
