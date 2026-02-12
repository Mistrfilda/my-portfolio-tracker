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
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\Column(type: Types::STRING, enumType: StockAiAnalysisResultTypeEnum::class)]
	private StockAiAnalysisResultTypeEnum $type;

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

	public function __construct(
		StockAiAnalysisRun $stockAiAnalysisRun,
		StockAsset $stockAsset,
		StockAiAnalysisResultTypeEnum $type,
		string|null $positiveNews,
		string|null $negativeNews,
		string|null $interestingNews,
		string|null $aiOpinion,
		StockAiAnalysisActionSuggestionEnum|null $actionSuggestion,
		string|null $reasoning,
		string|null $news,
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
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getStockAiAnalysisRun(): StockAiAnalysisRun
	{
		return $this->stockAiAnalysisRun;
	}

	public function getStockAsset(): StockAsset
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

}
