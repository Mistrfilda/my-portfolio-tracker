<?php

declare(strict_types = 1);

namespace App\Cash\Income\Bank;

use App\Admin\AppAdmin;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<BankIncome>
 */
class BankIncomeRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): BankIncome
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): BankIncome
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('bankIncome.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankIncome);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	public function findByIdentifier(
		string $identifier,
		BankAccount $bankAccount,
		LockModeEnum|null $lockMode = null,
	): BankIncome|null
	{
		$qb = $this->createQueryBuilderForAppAdmin($bankAccount->getAppAdmin());
		$qb->andWhere($qb->expr()->eq('bankIncome.identifier', ':identifier'));
		$qb->setParameter('identifier', $identifier);
		$qb->andWhere($qb->expr()->eq('bankIncome.bankAccount', ':bankAccount'));
		$qb->setParameter('bankAccount', $bankAccount);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankIncome);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<BankIncome>
	 */
	public function findByYearAndMonthAndBankAccountType(
		int|null $year,
		int|null $month,
		BankAccountTypeEnum $bankAccountTypeEnum,
	): array
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();

		$qb->leftJoin('bankIncome.bankAccount', 'bankAccount');
		$qb->andWhere($qb->expr()->eq('bankAccount.type', ':type'));
		$qb->setParameter('type', $bankAccountTypeEnum);

		if ($month !== null) {
			$qb->andWhere(
				$qb->expr()->eq('MONTH(bankIncome.settlementDate)', ':month'),
			);
			$qb->setParameter('month', $month);
		}

		if ($year !== null) {
			$qb->andWhere(
				$qb->expr()->eq('YEAR(bankIncome.settlementDate)', ':year'),
			);
			$qb->setParameter('year', $year);
		}

		$qb->orderBy('bankIncome.amount', 'ASC');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<BankIncome>
	 */
	public function findAll(): array
	{
		return $this->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<BankIncome>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('bankIncome.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface $id): string => $id->toString(), $ids));

		return $qb->getQuery()->getResult();
	}

	/** @return array<BankIncome> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('bankIncome.bankAccount', 'ownerBankAccount');
		$this->addAppAdminToQueryBuilder($qb, 'ownerBankAccount', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('bankIncome');
	}

}
