<?php

declare(strict_types = 1);

namespace App\Portu\Position;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortuPosition>
 */
class PortuPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): PortuPosition
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): PortuPosition
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('portuPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortuPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<PortuPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface|string> $ids
	 * @return array<PortuPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('portuPosition.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface|string $id): string => (string) $id, $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<PortuPosition>
	 */
	public function findAllOpened(): array
	{
		return $this->findAll();
	}

	/** @return array<PortuPosition> */
	public function findAllOpenedForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForDatagrid(UuidInterface $portAssetId): QueryBuilder
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->innerJoin('portuPosition.portuAsset', 'portuAsset');
		$qb->andWhere($qb->expr()->eq('portuAsset.id', ':portuAssetId'));
		$qb->setParameter('portuAssetId', $portAssetId);

		return $qb;
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'portuPosition', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portuPosition');
	}

}
