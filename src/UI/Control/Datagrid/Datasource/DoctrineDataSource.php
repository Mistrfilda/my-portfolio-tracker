<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Datasource;

use App\Doctrine\IEntity;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Filter\FilterText;
use App\UI\Control\Datagrid\Filter\IFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Nette\Utils\Strings;
use Ramsey\Uuid\UuidInterface;

class DoctrineDataSource implements IDataSource
{

	public function __construct(private QueryBuilder $qb)
	{
	}

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 * @return array<string|int, IEntity>
	 */
	public function getData(int $offset, int $limit, ArrayCollection $filters): array
	{
		$this->qb
			->setFirstResult($offset)
			->setMaxResults($limit);

		$this->addFilterToQuery($filters, $this->qb);

		/** @var array<string|int, IEntity> $results */
		$results = $this->qb
			->getQuery()
			->getResult();

		return $results;
	}

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 */
	public function getCount(ArrayCollection $filters): int
	{
		$countQb = clone $this->qb;

		$countQb
			->select('count(:rootAlias)')
			->setParameter('rootAlias', sprintf('%s.*', $this->getRootAlias()));

		$this->addFilterToQuery($filters, $countQb);

		$result = $countQb->getQuery()->getSingleScalarResult();

		assert(is_string($result) || is_int($result));

		return (int) $result;
	}

	public function getValueForColumn(IColumn $column, IEntity $row): string
	{
		if ($column->getGetterMethod() !== null) {
			return $column->getGetterMethod()($row);
		}

		$getterMethod = 'get' . Strings::firstUpper($column->getColumn());
		if (method_exists($row, $getterMethod) === false) {
			throw new DoctrineDataSourceException(
				sprintf(
					'Missing getter %s in entity %s',
					$getterMethod,
					$row::class,
				),
			);
		}

		//@phpstan-ignore-next-line
		return $column->processValue($row->{$getterMethod}());
	}

	public function getValueForKey(string $key, IEntity $row): string|int|float|UuidInterface
	{
		$getterMethod = 'get' . Strings::firstUpper($key);
		if (method_exists($row, $getterMethod) === false) {
			throw new DoctrineDataSourceException(
				sprintf(
					'Missing getter %s in entity %s',
					$getterMethod,
					$row::class,
				),
			);
		}

		//@phpstan-ignore-next-line
		return $row->{$getterMethod}();
	}

	private function getRootAlias(): string
	{
		$rootAliases = $this->qb->getRootAliases();
		if (array_key_exists(0, $rootAliases)) {
			return $rootAliases[0];
		}

		throw new DoctrineDataSourceException('Root alias not found');
	}

	/**
	 * @param ArrayCollection<string, IFilter> $filters
	 */
	private function addFilterToQuery(ArrayCollection $filters, QueryBuilder $qb): void
	{
		$rootAlias = $this->getRootAlias();
		$index = 0;
		foreach ($filters as $filter) {
			if ($filter->isValueSet() === false) {
				continue;
			}

			if ($filter instanceof FilterText) {
				$key = ':param_' . $index;
				$value = $filter->getValue();
				$qb->andWhere($qb->expr()->like(
					$rootAlias . '.' . $filter->getColumn()->getColumn(),
					$key,
				));

				$qb->setParameter($key, '%' . $value . '%');
			}

			$index++;
		}
	}

}
