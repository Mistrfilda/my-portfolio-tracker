<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('portfolio_performance_month')]
#[ORM\UniqueConstraint(name: 'portfolio_performance_month_unidx', fields: ['periodMonth'])]
class PortfolioPerformanceMonth implements Entity
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ImmutableDateTime $periodMonth;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $periodStartAt;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $periodEndAt;

	#[ORM\Column(type: Types::FLOAT)]
	private float $investedAtStart;

	#[ORM\Column(type: Types::FLOAT)]
	private float $investedAtEnd;

	#[ORM\Column(type: Types::FLOAT)]
	private float $valueAtStart;

	#[ORM\Column(type: Types::FLOAT)]
	private float $valueAtEnd;

	#[ORM\Column(type: Types::FLOAT)]
	private float $realizedProfit;

	#[ORM\Column(type: Types::FLOAT)]
	private float $netDividends;

	#[ORM\Column(type: Types::FLOAT)]
	private float $cashAtStart;

	#[ORM\Column(type: Types::FLOAT)]
	private float $cashAtEnd;

	#[ORM\Column(type: Types::FLOAT)]
	private float $externalContribution;

	#[ORM\Column(type: Types::FLOAT)]
	private float $returnFactor;

	public function __construct(
		ImmutableDateTime $periodMonth,
		ImmutableDateTime $periodStartAt,
		ImmutableDateTime $periodEndAt,
		float $investedAtStart,
		float $investedAtEnd,
		float $valueAtStart,
		float $valueAtEnd,
		float $realizedProfit,
		float $netDividends,
		float $cashAtStart,
		float $cashAtEnd,
		float $externalContribution,
		float $returnFactor,
		ImmutableDateTime $now,
	)
	{
		$this->periodMonth = $periodMonth;
		$this->periodStartAt = $periodStartAt;
		$this->periodEndAt = $periodEndAt;
		$this->investedAtStart = $investedAtStart;
		$this->investedAtEnd = $investedAtEnd;
		$this->valueAtStart = $valueAtStart;
		$this->valueAtEnd = $valueAtEnd;
		$this->realizedProfit = $realizedProfit;
		$this->netDividends = $netDividends;
		$this->cashAtStart = $cashAtStart;
		$this->cashAtEnd = $cashAtEnd;
		$this->externalContribution = $externalContribution;
		$this->returnFactor = $returnFactor;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getPeriodMonth(): ImmutableDateTime
	{
		return $this->periodMonth;
	}

	public function getPeriodStartAt(): ImmutableDateTime
	{
		return $this->periodStartAt;
	}

	public function getPeriodEndAt(): ImmutableDateTime
	{
		return $this->periodEndAt;
	}

	public function getInvestedAtStart(): float
	{
		return $this->investedAtStart;
	}

	public function getInvestedAtEnd(): float
	{
		return $this->investedAtEnd;
	}

	public function getValueAtStart(): float
	{
		return $this->valueAtStart;
	}

	public function getValueAtEnd(): float
	{
		return $this->valueAtEnd;
	}

	public function getRealizedProfit(): float
	{
		return $this->realizedProfit;
	}

	public function getNetDividends(): float
	{
		return $this->netDividends;
	}

	public function getCashAtStart(): float
	{
		return $this->cashAtStart;
	}

	public function getCashAtEnd(): float
	{
		return $this->cashAtEnd;
	}

	public function getExternalContribution(): float
	{
		return $this->externalContribution;
	}

	public function getReturnFactor(): float
	{
		return $this->returnFactor;
	}

	public function getAccountValueAtStart(): float
	{
		return $this->valueAtStart + $this->cashAtStart;
	}

	public function getAccountValueAtEnd(): float
	{
		return $this->valueAtEnd + $this->cashAtEnd;
	}

	public function getMidpoint(): ImmutableDateTime
	{
		$timestamp = (int) floor(
			($this->periodStartAt->getTimestamp() + $this->periodEndAt->getTimestamp()) / 2,
		);

		return new ImmutableDateTime('@' . $timestamp);
	}

}
