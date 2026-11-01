<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Admin\AppAdmin;
use App\Utils\TypeValidator;
use Doctrine\DBAL\Connection;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

class PortfolioStatisticCache
{

	private readonly Cache $cache;

	public function __construct(Storage $storage, private readonly Connection $connection)
	{
		$this->cache = new Cache($storage, self::class);
	}

	/**
	 * @template T
	 * @param callable(): T $build
	 * @return T
	 */
	public function load(AppAdmin $appAdmin, string $key, callable $build): mixed
	{
		// A snapshot and its performance history may still be rolled back by the batch.
		if ($this->connection->isTransactionActive()) {
			return $build();
		}

		$ownerId = $appAdmin->getId()->toString();
		// Capture revisions before calculating: an invalidated in-flight result stays unreachable.
		$cacheKey = ['v1', $ownerId, $this->getRevision('market'), $this->getRevision($ownerId), $key];

		/** @phpstan-var T $value */
		$value = $this->cache->load($cacheKey, $build, [Cache::Expire => '7 days']);
		return $value;
	}

	public function invalidate(AppAdmin $appAdmin): void
	{
		$this->cache->remove(['revision', $appAdmin->getId()->toString()]);
	}

	public function invalidateAll(): void
	{
		$this->cache->remove(['revision', 'market']);
	}

	private function getRevision(string $scope): string
	{
		$revision = $this->cache->load(
			['revision', $scope],
			static fn (): string => bin2hex(random_bytes(16)),
		);
		return TypeValidator::validateString($revision);
	}

}
