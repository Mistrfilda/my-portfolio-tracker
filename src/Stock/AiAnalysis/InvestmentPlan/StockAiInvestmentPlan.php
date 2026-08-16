<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use App\Asset\Price\AssetPriceEmbeddable;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

#[ORM\Entity]
#[ORM\Table(name: 'stock_ai_investment_plan')]
class StockAiInvestmentPlan implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAiAnalysisRun::class)]
	#[ORM\JoinColumn(nullable: false)]
	private StockAiAnalysisRun $referenceAnalysisRun;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class, columnPrefix: 'requested_amount_')]
	private AssetPriceEmbeddable $requestedAmount;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class, columnPrefix: 'requested_amount_czk_')]
	private AssetPriceEmbeddable $requestedAmountInCzk;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class, columnPrefix: 'portfolio_value_')]
	private AssetPriceEmbeddable $portfolioValue;

	#[ORM\Column(type: Types::FLOAT)]
	private float $requestedAmountToPortfolioPercent;

	#[ORM\Column(type: Types::FLOAT)]
	private float $requestedAmountToProjectedPortfolioPercent;

	#[ORM\Column(type: Types::TEXT)]
	private string $generatedPrompt;

	#[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
	private int $schemaVersion = 1;

	/** @var array<string, mixed> */
	#[ORM\Column(type: Types::JSON)]
	private array $inputSnapshot;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $rawResponse = null;

	/** @var array<string, mixed>|null */
	#[ORM\Column(type: Types::JSON, nullable: true)]
	private array|null $structuredData = null;

	#[ORM\Column(type: Types::STRING, nullable: true, enumType: StockAiAnalysisProcessingSourceEnum::class)]
	private StockAiAnalysisProcessingSourceEnum|null $processingSource = null;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $processedAt = null;

	/**
	 * @param array<string, mixed> $inputSnapshot
	 */
	public function __construct(
		StockAiAnalysisRun $referenceAnalysisRun,
		AssetPriceEmbeddable $requestedAmount,
		AssetPriceEmbeddable $requestedAmountInCzk,
		AssetPriceEmbeddable $portfolioValue,
		float $requestedAmountToPortfolioPercent,
		float $requestedAmountToProjectedPortfolioPercent,
		string $generatedPrompt,
		array $inputSnapshot,
		ImmutableDateTime $now,
		UuidInterface|null $id = null,
	)
	{
		if ($requestedAmount->getPrice() <= 0 || $requestedAmountInCzk->getPrice() <= 0) {
			throw new InvalidArgumentException('Investment amount must be greater than zero.');
		}

		if ($portfolioValue->getPrice() <= 0) {
			throw new InvalidArgumentException('Current stock portfolio value must be greater than zero.');
		}

		$this->id = $id ?? Uuid::uuid4();
		$this->referenceAnalysisRun = $referenceAnalysisRun;
		$this->requestedAmount = $requestedAmount;
		$this->requestedAmountInCzk = $requestedAmountInCzk;
		$this->portfolioValue = $portfolioValue;
		$this->requestedAmountToPortfolioPercent = $requestedAmountToPortfolioPercent;
		$this->requestedAmountToProjectedPortfolioPercent = $requestedAmountToProjectedPortfolioPercent;
		$this->generatedPrompt = $generatedPrompt;
		$this->inputSnapshot = $inputSnapshot;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	/**
	 * @param array<string, mixed> $structuredData
	 */
	public function complete(
		string $rawResponse,
		array $structuredData,
		StockAiAnalysisProcessingSourceEnum $processingSource,
		ImmutableDateTime $now,
	): void
	{
		if ($this->processedAt !== null) {
			throw new RuntimeException('Investment plan has already been processed.');
		}

		$this->rawResponse = $rawResponse;
		$this->structuredData = $structuredData;
		$this->processingSource = $processingSource;
		$this->processedAt = $now;
		$this->updatedAt = $now;
	}

	public function getReferenceAnalysisRun(): StockAiAnalysisRun
	{
		return $this->referenceAnalysisRun;
	}

	public function getRequestedAmount(): AssetPriceEmbeddable
	{
		return $this->requestedAmount;
	}

	public function getRequestedAmountInCzk(): AssetPriceEmbeddable
	{
		return $this->requestedAmountInCzk;
	}

	public function getPortfolioValue(): AssetPriceEmbeddable
	{
		return $this->portfolioValue;
	}

	public function getRequestedAmountToPortfolioPercent(): float
	{
		return $this->requestedAmountToPortfolioPercent;
	}

	public function getRequestedAmountToProjectedPortfolioPercent(): float
	{
		return $this->requestedAmountToProjectedPortfolioPercent;
	}

	public function getGeneratedPrompt(): string
	{
		return $this->generatedPrompt;
	}

	public function getSchemaVersion(): int
	{
		return $this->schemaVersion;
	}

	/** @return array<string, mixed> */
	public function getInputSnapshot(): array
	{
		return $this->inputSnapshot;
	}

	public function getRawResponse(): string|null
	{
		return $this->rawResponse;
	}

	/** @return array<string, mixed>|null */
	public function getStructuredData(): array|null
	{
		return $this->structuredData;
	}

	public function getProcessingSource(): StockAiAnalysisProcessingSourceEnum|null
	{
		return $this->processingSource;
	}

	public function getProcessedAt(): ImmutableDateTime|null
	{
		return $this->processedAt;
	}

	public function canImportCodexResponse(): bool
	{
		return $this->processedAt === null;
	}

	/** @return array<string, mixed> */
	public function getDecision(): array
	{
		return $this->getStructuredObject('decision');
	}

	/** @return array<int, array<string, mixed>> */
	public function getAllocations(): array
	{
		return $this->getStructuredList('allocations');
	}

	/** @return array<string, mixed> */
	public function getPortfolioImpact(): array
	{
		return $this->getStructuredObject('portfolioImpact');
	}

	/** @return array<int, array<string, mixed>> */
	public function getAlternatives(): array
	{
		return $this->getStructuredList('alternatives');
	}

	/** @return array<int, string> */
	public function getKeyRisks(): array
	{
		$items = $this->structuredData['keyRisks'] ?? [];
		if (!is_array($items)) {
			return [];
		}

		return array_values(array_filter($items, static fn (mixed $item): bool => is_string($item)));
	}

	/** @return array<string, mixed> */
	private function getStructuredObject(string $key): array
	{
		$value = $this->structuredData[$key] ?? null;
		if (!is_array($value) || array_is_list($value)) {
			return [];
		}

		return $this->normalizeObject($value);
	}

	/** @return array<int, array<string, mixed>> */
	private function getStructuredList(string $key): array
	{
		$value = $this->structuredData[$key] ?? null;
		if (!is_array($value)) {
			return [];
		}

		$result = [];
		foreach ($value as $item) {
			if (is_array($item)) {
				$result[] = $this->normalizeObject($item);
			}
		}

		return $result;
	}

	/**
	 * @param array<mixed> $data
	 * @return array<string, mixed>
	 */
	private function normalizeObject(array $data): array
	{
		$result = [];
		foreach ($data as $key => $value) {
			if (is_string($key)) {
				$result[$key] = $value;
			}
		}

		return $result;
	}

}
