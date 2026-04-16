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
#[ORM\Table(name: 'portfolio_report_dividend')]
class PortfolioReportDividend implements Entity
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: PortfolioReport::class, inversedBy: 'dividends')]
	#[ORM\JoinColumn(nullable: false)]
	private PortfolioReport $portfolioReport;

	#[ORM\Column(type: Types::STRING)]
	private string $ticker;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $paymentDate;

	#[ORM\Column(type: Types::FLOAT)]
	private float $amountInSourceCurrency;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $sourceCurrency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $amountCzk;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $netAmount = null;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $taxPercentage = null;

	public function __construct(
		PortfolioReport $portfolioReport,
		string $ticker,
		string $name,
		ImmutableDateTime $paymentDate,
		float $amountInSourceCurrency,
		CurrencyEnum $sourceCurrency,
		float $amountCzk,
		float|null $netAmount,
		float|null $taxPercentage,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->portfolioReport = $portfolioReport;
		$this->ticker = $ticker;
		$this->name = $name;
		$this->paymentDate = $paymentDate;
		$this->amountInSourceCurrency = $amountInSourceCurrency;
		$this->sourceCurrency = $sourceCurrency;
		$this->amountCzk = $amountCzk;
		$this->netAmount = $netAmount;
		$this->taxPercentage = $taxPercentage;
		$this->createdAt = $now;
	}

	public function getPortfolioReport(): PortfolioReport
	{
		return $this->portfolioReport;
	}

	public function getTicker(): string
	{
		return $this->ticker;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getPaymentDate(): ImmutableDateTime
	{
		return $this->paymentDate;
	}

	public function getAmountInSourceCurrency(): float
	{
		return $this->amountInSourceCurrency;
	}

	public function getSourceCurrency(): CurrencyEnum
	{
		return $this->sourceCurrency;
	}

	public function getAmountCzk(): float
	{
		return $this->amountCzk;
	}

	public function getNetAmount(): float|null
	{
		return $this->netAmount;
	}

	public function getTaxPercentage(): float|null
	{
		return $this->taxPercentage;
	}

}
