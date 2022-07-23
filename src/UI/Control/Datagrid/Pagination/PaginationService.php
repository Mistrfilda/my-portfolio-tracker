<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Pagination;

use function ceil;
use function count;
use function usort;

class PaginationService
{

	/**
	 * @return array<PaginationItem>
	 */
	public function getPagination(int $offset, int $limit, int $count): array
	{
		$items = [];

		$lastItemNumber = (int) ceil($count / $limit);
		$currentActive = (int) (($offset / $limit) + 1);

		//FIRST ELEMENT
		$items[] = new PaginationItem(
			1,
			'1',
			0,
			false,
			true,
			$count < $limit,
			$offset === 0,
		);

		//RETURN IF LESS THEN LIMIT OF LINES
		if ($count < $limit) {
			return $items;
		}

		//CALCULATE IF ITEMS ARE LESS THAN 7 * THIS.LIMIT AND RETURN
		if ($lastItemNumber < 11) {
			for ($i = 2; $i < 11; $i++) {
				if ($lastItemNumber < $i) {
					return $items;
				}

				$items[] = new PaginationItem(
					$i,
					(string) $i,
					$limit * ($i - 1),
					false,
					true,
					$count <= $limit * $i,
					$offset === $limit * ($i - 1),
				);
			}

			return $items;
		}

		if ($currentActive !== 1 && $currentActive !== $lastItemNumber) {
			//PUSH CURRENT ACTIVE
			$items[] = new PaginationItem(
				$currentActive,
				(string) $currentActive,
				$limit * ($currentActive - 1),
				false,
				true,
				$count <= $limit * $currentActive,
				$offset === $limit * ($currentActive - 1),
			);
		}

		$rightValue = 0;
		$leftValue = 0;

		for ($currentActiveHelper = 0; $currentActiveHelper < 2; $currentActiveHelper++) {
			//TO THE RIGHT
			$rightValue = $currentActive + $currentActiveHelper + 1;

			if ($rightValue < $lastItemNumber) {
				$items[] = new PaginationItem(
					$rightValue,
					(string) $rightValue,
					$limit * ($rightValue - 1),
					false,
					true,
					$count <= $limit * $rightValue,
					$offset === $limit * ($rightValue - 1),
				);
			}

			//TO THE LEFT
			$leftValue = $currentActive + ($currentActiveHelper + 1) * -1;
			if ($leftValue > 1) {
				$items[] = new PaginationItem(
					$leftValue,
					(string) $leftValue,
					$limit * ($leftValue - 1),
					false,
					true,
					$count <= $limit * $leftValue,
					$offset === $limit * ($leftValue - 1),
				);
			}
		}

		//LAST ELEMENT
		$items[] = new PaginationItem(
			$lastItemNumber,
			(string) $lastItemNumber,
			$limit * ($lastItemNumber - 1),
			false,
			true,
			$count <= $limit * $lastItemNumber,
			$offset === $limit * ($lastItemNumber - 1),
		);

		//PUSH MIDDLE ELEMENT ON FIRST AND LAST PAGE
		if (count($items) < 7) {
			$middleElement = (int) ceil($lastItemNumber / 2);

			$items[] = new PaginationItem(
				$middleElement,
				(string) $middleElement,
				$limit * ($middleElement - 1),
				false,
				true,
				$count <= $limit * $lastItemNumber,
				false,
			);

			//AROUND MIDDLE ELEMENT
			$items[] = $this->createBlankPaginatorItem($middleElement + 1);

			//AROUND MIDDLE ELEMENT
			$items[] = $this->createBlankPaginatorItem($middleElement - 1);

			//ON SECOND ELEMENT TO FIT LENGTH OF 9
			if (count($items) === 7) {
				$items[] = $this->createBlankPaginatorItem($middleElement + 2);
				$items[] = $this->createBlankPaginatorItem($middleElement - 2);
			}

			if (count($items) === 8) {
				$items[] = $currentActive < 5
					? $this->createBlankPaginatorItem($middleElement + 2)
					: $this->createBlankPaginatorItem($middleElement - 2);
			}
		} else {
			//PUSH VALUES AROUND FIRST AND LAST
			if ($leftValue - 1 > 1) {
				$items[] = $this->createBlankPaginatorItem($leftValue - 1);
			}

			if ($rightValue + 1 < $lastItemNumber) {
				$items[] = $this->createBlankPaginatorItem($rightValue + 1);
			}

			if (count($items) === 8) {
				$items[] = $rightValue + 1 < $lastItemNumber
					? $this->createBlankPaginatorItem($rightValue + 2)
					: $this->createBlankPaginatorItem($leftValue - 2);
			}
		}

		usort($items, static function (PaginationItem $a, PaginationItem $b) {
			if ($a->getId() < $b->getId()) {
				return -1;
			}

			return 1;
		});

		return $items;
	}

	private function createBlankPaginatorItem(int $id): PaginationItem
	{
		return new PaginationItem($id, '...', 0, true, false, false, false);
	}

}
