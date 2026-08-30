<?php

declare(strict_types = 1);

namespace App\Test\Integration\Cash\Expense\Bank;

use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Currency\CurrencyEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class BankExpenseRepositoryTest extends IntegrationTestCase
{

	private BankExpenseRepository $bankExpenseRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->bankExpenseRepository = $this->getService(BankExpenseRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testFindByEffectiveDateRangeExcludingCategory(): void
	{
		$startInclusive = new ImmutableDateTime('2091-01-01 00:00:00');
		$endExclusive = new ImmutableDateTime('2091-02-01 00:00:00');
		$bankAccount = new BankAccount('FIRE test', 'Test bank', BankAccountTypeEnum::PERSONAL);
		$investmentCategory = new ExpenseCategory(
			'Investment',
			ExpenseCategoryEnum::INVESTMENT,
			$startInclusive,
		);
		$restaurantCategory = new ExpenseCategory(
			'Restaurant',
			ExpenseCategoryEnum::RESTAURANT,
			$startInclusive,
		);
		$investmentTag = new ExpenseTag(
			'Investment',
			$investmentCategory,
			null,
			[],
			$startInclusive,
			false,
		);
		$restaurantTag = new ExpenseTag(
			'Restaurant',
			$restaurantCategory,
			null,
			[],
			$startInclusive,
			false,
		);

		$atStart = $this->createBankExpense(
			'fire-effective-date-at-start',
			$bankAccount,
			new ImmutableDateTime('2090-12-01'),
			new ImmutableDateTime('2091-01-25'),
			$startInclusive,
		);
		$nonInvestment = $this->createBankExpense(
			'fire-effective-date-non-investment',
			$bankAccount,
			new ImmutableDateTime('2090-12-01'),
			new ImmutableDateTime('2090-12-15'),
			new ImmutableDateTime('2091-01-05'),
			$restaurantTag,
		);
		$settlementFallback = $this->createBankExpense(
			'fire-effective-date-settlement',
			$bankAccount,
			new ImmutableDateTime('2090-12-01'),
			new ImmutableDateTime('2091-01-10'),
			null,
		);
		$createdAtFallback = $this->createBankExpense(
			'fire-effective-date-created-at',
			$bankAccount,
			new ImmutableDateTime('2091-01-15'),
			null,
			null,
		);
		$investment = $this->createBankExpense(
			'fire-effective-date-investment',
			$bankAccount,
			new ImmutableDateTime('2090-12-01'),
			null,
			new ImmutableDateTime('2091-01-20'),
			$investmentTag,
		);
		$transactionDateTakesPrecedence = $this->createBankExpense(
			'fire-effective-date-precedence',
			$bankAccount,
			new ImmutableDateTime('2091-01-12'),
			new ImmutableDateTime('2091-01-12'),
			new ImmutableDateTime('2090-12-31 23:59:59'),
		);
		$atEnd = $this->createBankExpense(
			'fire-effective-date-at-end',
			$bankAccount,
			new ImmutableDateTime('2090-12-01'),
			null,
			$endExclusive,
		);

		$this->entityManager->persist($bankAccount);
		$this->entityManager->persist($investmentCategory);
		$this->entityManager->persist($restaurantCategory);
		$this->entityManager->persist($investmentTag);
		$this->entityManager->persist($restaurantTag);
		$this->entityManager->persist($atStart);
		$this->entityManager->persist($nonInvestment);
		$this->entityManager->persist($settlementFallback);
		$this->entityManager->persist($createdAtFallback);
		$this->entityManager->persist($investment);
		$this->entityManager->persist($transactionDateTakesPrecedence);
		$this->entityManager->persist($atEnd);
		$this->entityManager->flush();
		$this->entityManager->clear();

		$result = $this->bankExpenseRepository->findByEffectiveDateRangeExcludingCategory(
			$startInclusive,
			$endExclusive,
			ExpenseCategoryEnum::INVESTMENT,
		);

		self::assertSame(
			[
				'fire-effective-date-at-start',
				'fire-effective-date-non-investment',
				'fire-effective-date-settlement',
				'fire-effective-date-created-at',
			],
			array_map(
				static fn (BankExpense $bankExpense): string => $bankExpense->getIdentifier(),
				$result,
			),
		);
	}

	private function createBankExpense(
		string $identifier,
		BankAccount $bankAccount,
		ImmutableDateTime $createdAt,
		ImmutableDateTime|null $settlementDate,
		ImmutableDateTime|null $transactionDate,
		ExpenseTag|null $mainTag = null,
	): BankExpense
	{
		$bankExpense = new BankExpense(
			$identifier,
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::CARD_PAYMENT,
			100.0,
			CurrencyEnum::CZK,
			$settlementDate,
			$transactionDate,
			$identifier,
			$createdAt,
			$bankAccount,
		);

		if ($mainTag !== null) {
			$bankExpense->setMainTag($mainTag);
		}

		return $bankExpense;
	}

}
