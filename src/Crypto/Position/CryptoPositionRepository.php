<?php

declare(strict_types = 1);

namespace App\Crypto\Position;

use App\Admin\AppAdmin;
use App\Currency\CurrencyEnum;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<CryptoPosition>
 */
class CryptoPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): CryptoPosition
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): CryptoPosition
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('cryptoPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof CryptoPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<CryptoPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @return array<CryptoPosition>
	 */
	public function findAllOpened(): array
	{
		$positions = $this->findAll();
		foreach ($positions as $key => $position) {
			if ($position->isPositionClosed()) {
				unset($positions[$key]);
			}
		}

		return $positions;
	}

	/**
	 * @return array<CryptoPosition>
	 */
	public function findAllOpenedInCurrency(CurrencyEnum $currencyEnum): array
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('cryptoPosition.cryptoAsset', 'cryptoAsset');

		$qb->andWhere($qb->expr()->eq('cryptoAsset.mainConversionCurrency', ':currency'));
		$qb->setParameter('currency', $currencyEnum);

		$qb->andWhere($qb->expr()->isNull('cryptoPosition.cryptoClosedPosition'));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface|string> $ids
	 * @return array<CryptoPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('cryptoPosition.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface|string $id): string => (string) $id, $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForDatagrid(): QueryBuilder
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->innerJoin('cryptoPosition.cryptoAsset', 'cryptoAsset');

		return $qb;
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'cryptoPosition', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('cryptoPosition');
	}

}
