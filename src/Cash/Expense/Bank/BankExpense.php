<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Cash\Expense\Expense;
use App\Cash\Expense\ExpensePrice;
use App\Cash\Expense\ExpenseTypeEnum;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('bank_expense')]
class BankExpense implements Entity, Expense
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $identifier;

	#[ORM\Column(type: Types::STRING, enumType: BankSourceEnum::class)]
	private BankSourceEnum $source;

	#[ORM\Column(type: Types::STRING, enumType: BankTransactionType::class)]
	private BankTransactionType $bankTransactionType;

	#[ORM\Column(type: Types::FLOAT)]
	private float $amount;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $settlementDate;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $transactionDate;

	#[ORM\Column(type: Types::STRING)]
	private string $transactionRawContent;

	public function __construct(
		string $identifier,
		BankSourceEnum $source,
		BankTransactionType $bankTransactionType,
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime|null $settlementDate,
		ImmutableDateTime|null $transactionDate,
		string $transactionRawContent,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->identifier = $identifier;
		$this->source = $source;
		$this->bankTransactionType = $bankTransactionType;
		$this->amount = $amount;
		$this->currency = $currency;
		$this->settlementDate = $settlementDate;
		$this->transactionDate = $transactionDate;
		$this->transactionRawContent = $transactionRawContent;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getSource(): BankSourceEnum
	{
		return $this->source;
	}

	public function getAmount(): float
	{
		return $this->amount;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getSettlementDate(): ImmutableDateTime|null
	{
		return $this->settlementDate;
	}

	public function getTransactionDate(): ImmutableDateTime|null
	{
		return $this->transactionDate;
	}

	public function getTransactionRawContent(): string
	{
		return $this->transactionRawContent;
	}

	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	public function getDate(): ImmutableDateTime
	{
		if ($this->transactionDate !== null) {
			return $this->transactionDate;
		}

		if ($this->settlementDate !== null) {
			return $this->settlementDate;
		}

		return $this->createdAt;
	}

	public function getExpensePrice(): ExpensePrice
	{
		return new ExpensePrice($this->amount, $this->currency);
	}

	public function getBankTransactionType(): BankTransactionType
	{
		return $this->bankTransactionType;
	}

	public function getExpenseType(): ExpenseTypeEnum
	{
		return $this->bankTransactionType;
	}

}