<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Admin\AppAdmin;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<BankExpense>
 */
class BankExpenseRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): BankExpense
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): BankExpense
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('bankExpense.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankExpense);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	public function findByIdentifier(
		string $identifier,
		BankAccount $bankAccount,
		LockModeEnum|null $lockMode = null,
	): BankExpense|null
	{
		$qb = $this->createQueryBuilderForAppAdmin($bankAccount->getAppAdmin());
		$qb->andWhere($qb->expr()->eq('bankExpense.identifier', ':identifier'));
		$qb->setParameter('identifier', $identifier);
		$qb->andWhere($qb->expr()->eq('bankExpense.bankAccount', ':bankAccount'));
		$qb->setParameter('bankAccount', $bankAccount);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankExpense);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<BankExpense>
	 */
	public function findAll(): array
	{
		return $this->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<BankExpense>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('bankExpense.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface $id): string => $id->toString(), $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<BankExpense>
	 */
	public function findByEffectiveDateRangeExcludingCategory(
		ImmutableDateTime $startInclusive,
		ImmutableDateTime $endExclusive,
		ExpenseCategoryEnum $excludedCategory,
	): array
	{
		$effectiveDate = 'COALESCE(bankExpense.transactionDate, bankExpense.settlementDate, bankExpense.createdAt)';
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->addSelect('mainTag', 'expenseCategory');
		$qb->addSelect($effectiveDate . ' AS HIDDEN effectiveDate');
		$qb->leftJoin('mainTag.expenseCategory', 'expenseCategory');
		$qb->andWhere($qb->expr()->gte($effectiveDate, ':startInclusive'));
		$qb->andWhere($qb->expr()->lt($effectiveDate, ':endExclusive'));
		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->isNull('expenseCategory.enumName'),
				$qb->expr()->neq('expenseCategory.enumName', ':excludedCategory'),
			),
		);
		$qb->setParameter('startInclusive', $startInclusive);
		$qb->setParameter('endExclusive', $endExclusive);
		$qb->setParameter('excludedCategory', $excludedCategory);
		$qb->orderBy('effectiveDate', 'ASC');
		$qb->addOrderBy('bankExpense.id', 'ASC');

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<BankExpense>
	 */
	public function findByTagCategory(
		ExpenseCategory $expenseCategory,
		int|null $year,
		int|null $month,
	): array
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->innerJoin('bankExpense.mainTag', 'tag');

		$qb->andWhere(
			$qb->expr()->eq('tag.expenseCategory', ':expenseCategory'),
		);
		$qb->setParameter('expenseCategory', $expenseCategory);

		if ($month !== null) {
			$qb->andWhere($qb->expr()->eq('MONTH(bankExpense.settlementDate)', ':month'));
			$qb->setParameter('month', $month);
		}

		if ($year !== null) {
			$qb->andWhere($qb->expr()->eq('YEAR(bankExpense.settlementDate)', ':year'));
			$qb->setParameter('year', $year);
		}

		$qb->orderBy('bankExpense.amount', 'ASC');

		return $qb->getQuery()->getResult();
	}

	/** @return array<BankExpense> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('bankExpense.bankAccount', 'ownerBankAccount');
		$this->addAppAdminToQueryBuilder($qb, 'ownerBankAccount', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankExpense');
		$qb->leftJoin('bankExpense.mainTag', 'mainTag');
		return $qb;
	}

}
