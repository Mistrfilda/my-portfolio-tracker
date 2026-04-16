<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'portfolio_report_asset_performance')]
class PortfolioReportAssetPerformance implements Entity
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: PortfolioReport::class, inversedBy: 'assetPerformances')]
	#[ORM\JoinColumn(nullable: false)]
	private PortfolioReport $portfolioReport;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioReportAssetRankingTypeEnum::class)]
	private PortfolioReportAssetRankingTypeEnum $rankingType;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioReportAssetRankingDirectionEnum::class)]
	private PortfolioReportAssetRankingDirectionEnum $direction;

	#[ORM\Column(type: Types::STRING)]
	private string $ticker;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $baseCurrency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $priceStartInBaseCurrency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $priceEndInBaseCurrency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $priceStartCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $priceEndCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $priceAbsoluteChange;

	#[ORM\Column(type: Types::FLOAT)]
	private float $pricePercentageChange;

	#[ORM\Column(type: Types::FLOAT)]
	private float $positionValueStartCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $positionValueEndCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $positionAbsoluteChangeCzk;

	#[ORM\Column(type: Types::FLOAT)]
	private float $contributionToPortfolioPercentage;

	public function __construct(
		PortfolioReport $portfolioReport,
		PortfolioReportAssetRankingTypeEnum $rankingType,
		PortfolioReportAssetRankingDirectionEnum $direction,
		string $ticker,
		string $name,
		CurrencyEnum $baseCurrency,
		float $priceStartInBaseCurrency,
		float $priceEndInBaseCurrency,
		float $priceStartCzk,
		float $priceEndCzk,
		float $positionValueStartCzk,
		float $positionValueEndCzk,
		float $contributionToPortfolioPercentage,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->portfolioReport = $portfolioReport;
		$this->rankingType = $rankingType;
		$this->direction = $direction;
		$this->ticker = $ticker;
		$this->name = $name;
		$this->baseCurrency = $baseCurrency;
		$this->priceStartInBaseCurrency = $priceStartInBaseCurrency;
		$this->priceEndInBaseCurrency = $priceEndInBaseCurrency;
		$this->priceStartCzk = $priceStartCzk;
		$this->priceEndCzk = $priceEndCzk;
		$this->priceAbsoluteChange = $priceEndInBaseCurrency - $priceStartInBaseCurrency;
		$this->pricePercentageChange = $priceStartInBaseCurrency === 0.0
			? 0.0
			: ($priceEndInBaseCurrency - $priceStartInBaseCurrency) / $priceStartInBaseCurrency * 100;
		$this->positionValueStartCzk = $positionValueStartCzk;
		$this->positionValueEndCzk = $positionValueEndCzk;
		$this->positionAbsoluteChangeCzk = $positionValueEndCzk - $positionValueStartCzk;
		$this->contributionToPortfolioPercentage = $contributionToPortfolioPercentage;
		$this->createdAt = $now;
	}

	public function getPortfolioReport(): PortfolioReport
	{
		return $this->portfolioReport;
	}

	public function getRankingType(): PortfolioReportAssetRankingTypeEnum
	{
		return $this->rankingType;
	}

	public function getDirection(): PortfolioReportAssetRankingDirectionEnum
	{
		return $this->direction;
	}

	public function getTicker(): string
	{
		return $this->ticker;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getBaseCurrency(): CurrencyEnum
	{
		return $this->baseCurrency;
	}

	public function getPriceStartInBaseCurrency(): float
	{
		return $this->priceStartInBaseCurrency;
	}

	public function getPriceEndInBaseCurrency(): float
	{
		return $this->priceEndInBaseCurrency;
	}

	public function getPriceStartCzk(): float
	{
		return $this->priceStartCzk;
	}

	public function getPriceEndCzk(): float
	{
		return $this->priceEndCzk;
	}

	public function getPriceAbsoluteChange(): float
	{
		return $this->priceAbsoluteChange;
	}

	public function getPricePercentageChange(): float
	{
		return $this->pricePercentageChange;
	}

	public function getPositionValueStartCzk(): float
	{
		return $this->positionValueStartCzk;
	}

	public function getPositionValueEndCzk(): float
	{
		return $this->positionValueEndCzk;
	}

	public function getPositionAbsoluteChangeCzk(): float
	{
		return $this->positionAbsoluteChangeCzk;
	}

	public function getContributionToPortfolioPercentage(): float
	{
		return $this->contributionToPortfolioPercentage;
	}

}
