<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Datasource;

use App\Doctrine\Entity;
use App\UI\Control\Datagrid\Column\IColumn;
use App\UI\Control\Datagrid\Filter\FilterDateRange;
use App\UI\Control\Datagrid\Filter\FilterNullState;
use App\UI\Control\Datagrid\Filter\FilterSelect;
use App\UI\Control\Datagrid\Filter\FilterText;
use App\UI\Control\Datagrid\Filter\IFilter;
use App\UI\Control\Datagrid\Sort\Sort;
use DateTimeImmutable;
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
	 * @param ArrayCollection<string, Sort> $sorts
	 * @return array<string|int, Entity>
	 */
	public function getData(int $offset, int $limit, ArrayCollection $filters, ArrayCollection $sorts): array
	{
		$this->qb
			->setFirstResult($offset)
			->setMaxResults($limit);

		$this->addFilterToQuery($filters, $this->qb);
		$this->addSortToQuery($sorts, $this->qb);

		/** @var array<string|int, Entity> $results */
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

	public function getValueForColumn(IColumn $column, Entity $row): string
	{
		if ($column->getGetterMethod() !== null) {
			//@phpstan-ignore-next-line
			return $column->processValue($column->getGetterMethod()($row));
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

	public function getValueForKey(string $key, Entity $row): string|int|float|UuidInterface
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

			$column = $filter->getReferencedColumn() ?? $rootAlias . '.' . $filter->getColumn();

			if ($filter instanceof FilterText) {
				$key = 'param_' . $index;
				$value = $filter->getValue($filter->getKey());

				$qb->andWhere($qb->expr()->like(
					$column,
					':' . $key,
				));

				$qb->setParameter($key, '%' . $value . '%');
			}

			if ($filter instanceof FilterDateRange) {
				$from = $filter->getValue($filter->getFromParameter());
				if ($from !== null) {
					$key = 'param_' . $index . '_from';
					$qb->andWhere($qb->expr()->gte($column, ':' . $key));
					$qb->setParameter($key, new DateTimeImmutable((string) $from));
				}

				$to = $filter->getValue($filter->getToParameter());
				if ($to !== null) {
					$key = 'param_' . $index . '_to';
					$qb->andWhere($qb->expr()->lt($column, ':' . $key));
					$qb->setParameter($key, (new DateTimeImmutable((string) $to))->modify('+1 day'));
				}
			}

			if ($filter instanceof FilterNullState) {
				$value = $filter->getValue($filter->getKey());
				if ($value === FilterNullState::NULL) {
					$qb->andWhere($qb->expr()->isNull($column));
				}

				if ($value === FilterNullState::NOT_NULL) {
					$qb->andWhere($qb->expr()->isNotNull($column));
				}
			} elseif ($filter instanceof FilterSelect) {
				$key = 'param_' . $index;
				$qb->andWhere($qb->expr()->eq($column, ':' . $key));
				$qb->setParameter($key, $filter->getValue($filter->getKey()));
			}

			$index++;
		}
	}

	/**
	 * @param ArrayCollection<string, Sort> $sorts
	 */
	private function addSortToQuery(ArrayCollection $sorts, QueryBuilder $qb): void
	{
		$rootAlias = $this->getRootAlias();
		foreach ($sorts as $sort) {
			if ($sort->getCurrentDirection() === null) {
				continue;
			}

			$x = $sort->getColumn()->getReferencedColumn() !== null
				? $sort->getColumn()->getReferencedColumn()
				: $rootAlias . '.' . $sort->getColumn()->getColumn();

			$qb->addOrderBy(
				$x,
				$sort->getCurrentDirection()->value,
			);
		}
	}

}
