<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpenseData;
use JsonSerializable;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class KbTransaction implements BankExpenseData, JsonSerializable
{

	private string|null $settlementDate = null;

	private string|null $transactionDate = null;

	private string|null $transactionRawContent = null;

	private BankTransactionType $bankTransactionType;

	private float $amount;

	private string|null $unprocessedReason = null;

	public function getSettlementDate(): ImmutableDateTime|null
	{
		if ($this->settlementDate === null) {
			return null;
		}

		return DatetimeFactory::createFromFormat($this->settlementDate, 'd.m.Y');
	}

	public function setSettlementDate(string|null $settlementDate): void
	{
		$this->settlementDate = $settlementDate;
	}

	public function getTransactionDate(): ImmutableDateTime|null
	{
		if ($this->transactionDate === null) {
			return null;
		}

		return DatetimeFactory::createFromFormat($this->transactionDate, 'd.m.Y');
	}

	public function setTransactionDate(string|null $transactionDate): void
	{
		$this->transactionDate = $transactionDate;
	}

	public function getTransactionRawContent(): string|null
	{
		return $this->transactionRawContent;
	}

	public function setTransactionRawContent(string|null $transactionRawContent): void
	{
		$this->transactionRawContent = $transactionRawContent;
	}

	public function getBankTransactionType(): BankTransactionType
	{
		return $this->bankTransactionType;
	}

	public function setBankTransactionType(BankTransactionType $bankTransactionType): void
	{
		$this->bankTransactionType = $bankTransactionType;
	}

	public function getAmount(): float
	{
		return $this->amount;
	}

	public function setAmount(float $amount): void
	{
		$this->amount = $amount;
	}

	public function getUnprocessedReason(): string|null
	{
		return $this->unprocessedReason;
	}

	public function setUnprocessedReason(string $unprocessedReason): void
	{
		$this->unprocessedReason = $unprocessedReason;
	}

	/**
	 * @return array<mixed>
	 */
	public function jsonSerialize(): array
	{
		return [
			'settlementDate' => $this->getSettlementDate(),
			'transactionDate' => $this->getTransactionDate(),
			'amount' => $this->getAmount(),
		];
	}

}
