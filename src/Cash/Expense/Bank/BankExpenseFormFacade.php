<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Currency\CurrencyEnum;
use App\Tax\Payment\TaxPaymentRoleEnum;
use App\Tax\TaxYearReportFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BankExpenseFormFacade implements BankExpenseFacade
{

	public function __construct(
		private BankExpenseRepository $bankExpenseRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private BankAccountRepository $bankAccountRepository,
		private TaxYearReportFacade|null $taxYearReportFacade = null,
	)
	{
	}

	public function create(
		string|null $identifier,
		BankSourceEnum $source,
		BankTransactionType $bankTransactionType,
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime|null $settlementDate,
		ImmutableDateTime|null $transactionDate,
		string $transactionRawContent,
		UuidInterface $bankAccountId,
		TaxPaymentRoleEnum|null $taxPaymentRoleOverride = null,
		int|null $taxYearOverride = null,
	): BankExpense
	{
		if ($identifier === null) {
			$identifier = Uuid::uuid4()->toString();
		}

		$now = $this->datetimeFactory->createNow();
		$bankExpense = new BankExpense(
			$identifier,
			$source,
			$bankTransactionType,
			$amount,
			$currency,
			$settlementDate,
			$transactionDate,
			$transactionRawContent,
			$now,
			$this->bankAccountRepository->getById($bankAccountId),
		);

		if ($taxPaymentRoleOverride !== null || $taxYearOverride !== null) {
			$bankExpense->updateTaxClassification($taxPaymentRoleOverride, $taxYearOverride, $now);
		}

		$this->entityManager->persist($bankExpense);
		$this->entityManager->flush();

		if ($this->taxYearReportFacade !== null && $bankExpense->resolveTaxPaymentRole() !== null) {
			$this->enqueueTaxRefreshForExpense($bankExpense);
		}

		return $bankExpense;
	}

	public function update(
		UuidInterface $id,
		string|null $identifier,
		BankSourceEnum $source,
		BankTransactionType $bankTransactionType,
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime|null $settlementDate,
		ImmutableDateTime|null $transactionDate,
		string $transactionRawContent,
		UuidInterface $bankAccountId,
		TaxPaymentRoleEnum|null $taxPaymentRoleOverride = null,
		int|null $taxYearOverride = null,
	): BankExpense
	{
		$bankExpense = $this->bankExpenseRepository->getById($id);
		$previousRole = $this->taxYearReportFacade !== null ? $bankExpense->resolveTaxPaymentRole() : null;
		$previousYear = $this->taxYearReportFacade !== null
			? ($bankExpense->getTaxYearOverride() ?? $bankExpense->getDate()->getYear())
			: null;
		$now = $this->datetimeFactory->createNow();

		$bankExpense->update(
			$identifier,
			$source,
			$bankTransactionType,
			$amount,
			$currency,
			$settlementDate,
			$transactionDate,
			$transactionRawContent,
			$now,
			$this->bankAccountRepository->getById($bankAccountId),
		);
		$bankExpense->updateTaxClassification($taxPaymentRoleOverride, $taxYearOverride, $now);

		$this->entityManager->flush();

		if (
			$this->taxYearReportFacade !== null
			&& (
				$previousRole !== null
				|| $bankExpense->resolveTaxPaymentRole() !== null
			)
		) {
			$this->enqueueTaxRefreshForExpense($bankExpense, $previousYear);
		}

		return $bankExpense;
	}

	public function updateTaxClassification(
		UuidInterface $id,
		TaxPaymentRoleEnum|null $taxPaymentRoleOverride,
		int|null $taxYearOverride,
	): BankExpense
	{
		$bankExpense = $this->bankExpenseRepository->getById($id);
		$previousRole = $this->taxYearReportFacade !== null ? $bankExpense->resolveTaxPaymentRole() : null;
		$previousYear = $this->taxYearReportFacade !== null
			? ($bankExpense->getTaxYearOverride() ?? $bankExpense->getDate()->getYear())
			: null;
		$bankExpense->updateTaxClassification(
			$taxPaymentRoleOverride,
			$taxYearOverride,
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->flush();

		if (
			$this->taxYearReportFacade !== null
			&& (
				$previousRole !== null
				|| $bankExpense->resolveTaxPaymentRole() !== null
			)
		) {
			$this->enqueueTaxRefreshForExpense($bankExpense, $previousYear);
		}

		return $bankExpense;
	}

	private function enqueueTaxRefreshForExpense(BankExpense $bankExpense, int|null $additionalYear = null): void
	{
		if ($this->taxYearReportFacade === null) {
			return;
		}

		$date = $bankExpense->getDate();
		$years = [
			$bankExpense->getTaxYearOverride() ?? $date->getYear(),
		];

		if ($bankExpense->getTaxYearOverride() === null && $date->getMonth() === 1 && $date->getDay() <= 10) {
			$years[] = $date->getYear() - 1;
		}

		if ($additionalYear !== null) {
			$years[] = $additionalYear;
		}

		$owner = $bankExpense->getBankAccount()->getAppAdmin();
		foreach (array_unique($years) as $year) {
			$this->taxYearReportFacade->enqueueOpenYearRefresh($year, $owner);
		}
	}

}
