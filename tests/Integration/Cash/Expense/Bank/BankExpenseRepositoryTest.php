<?php

declare(strict_types = 1);

namespace App\Test\Integration\Cash\Expense\Bank;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\Currency\CurrencyEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

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
		$username = 'fire-' . Uuid::uuid4()->toString();
		$appAdmin = new AppAdmin(
			$username,
			$username,
			$username . '@example.test',
			'unused',
			$startInclusive,
			false,
			false,
		);
		$this->entityManager->persist($appAdmin);
		$this->getService(CurrentAppAdminGetter::class)->setApiAppAdmin($appAdmin);
		$bankAccount = new BankAccount($appAdmin, 'FIRE test', 'Test bank', BankAccountTypeEnum::PERSONAL);
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
			$appAdmin,
			'Investment',
			$investmentCategory,
			null,
			[],
			$startInclusive,
			false,
		);
		$restaurantTag = new ExpenseTag(
			$appAdmin,
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

	public function testOverviewIncludesUntaggedExpensesAndStandaloneTagsWithoutCrossingOwners(): void
	{
		$now = new ImmutableDateTime('2092-01-01');
		$owner = new AppAdmin(
			'overview-owner',
			'Overview owner',
			'overview-owner@example.test',
			'unused',
			$now,
			false,
			false,
		);
		$otherOwner = new AppAdmin(
			'overview-other',
			'Other owner',
			'overview-other@example.test',
			'unused',
			$now,
			false,
			false,
		);
		$account = new BankAccount($owner, 'Overview', 'Test', BankAccountTypeEnum::PERSONAL);
		$otherAccount = new BankAccount($otherOwner, 'Other', 'Test', BankAccountTypeEnum::PERSONAL);
		$extra = new ExpenseTag($owner, 'Context', null, null, [], $now, false);
		$secondExtra = new ExpenseTag($owner, 'Gift', null, null, [], $now, false);
		$untagged = $this->createBankExpense('overview-untagged', $account, $now, $now, null);
		$untagged->addOtherTag($extra);
		$untagged->addOtherTag($secondExtra);
		$otherExpense = $this->createBankExpense('overview-other', $otherAccount, $now, $now, null);
		$nextMonth = $this->createBankExpense(
			'overview-february',
			$account,
			$now,
			new ImmutableDateTime('2092-02-01'),
			null,
		);
		foreach ([$owner, $otherOwner, $account, $otherAccount, $extra, $secondExtra, $untagged, $otherExpense, $nextMonth] as $entity) {
			$this->entityManager->persist($entity);
		}

		$this->entityManager->flush();
		$this->getService(CurrentAppAdminGetter::class)->setApiAppAdmin($owner);
		$result = $this->bankExpenseRepository->findForOverview(2092, 1);
		self::assertSame([$untagged], $result);
		self::assertCount(2, $result[0]->getOtherTags());
		self::assertCount(2, $this->bankExpenseRepository->findForOverview(2092, null));
		$tags = $this->getService(ExpenseTagRepository::class);
		self::assertSame([], $tags->findAllMain());
		self::assertCount(2, $tags->findAllOtherTags());
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
