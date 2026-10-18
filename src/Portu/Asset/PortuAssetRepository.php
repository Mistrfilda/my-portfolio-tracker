<?php

declare(strict_types = 1);

namespace App\Portu\Asset;

use App\Admin\AppAdmin;
use App\Asset\Asset;
use App\Asset\AssetRepository;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortuAsset>
 */
class PortuAssetRepository extends BaseRepository implements AssetRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): PortuAsset
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): PortuAsset
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('portuAsset.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortuAsset);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<Asset>
	 */
	public function getAllActiveAssets(): array
	{
		return $this->findAll();
	}

	/**
	 * @return array<PortuAsset>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface|string> $ids
	 * @return array<PortuAsset>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('portuAsset.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface|string $id): string => (string) $id, $ids));

		return $qb->getQuery()->getResult();
	}

	/** @return array<PortuAsset> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'portuAsset', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portuAsset');
	}

}
