<?php

declare(strict_types = 1);

namespace App\Utils\Sortable;

class SortService
{

	public function setNewPosition(
		ISortableEntity $entity,
		ISortableEntity|null $previous,
		ISortableEntity|null $next,
	): void
	{
		if ($next === null && $previous === null) {
			throw new IncompatibleSortableEntitiesException('At least one of `previous` and `next` must be set');
		}

		if ($previous !== null && $entity->getSortingGroup() !== $previous->getSortingGroup()) {
			$msg = 'Previous does not belong to the same sorting group as sorted entity';

			throw new IncompatibleSortableEntitiesException($msg);
		}

		if ($next !== null && $entity->getSortingGroup() !== $next->getSortingGroup()) {
			$msg = 'Next does not belong to the same sorting group as sorted entity';

			throw new IncompatibleSortableEntitiesException($msg);
		}

		$position = $entity->getSortPosition();
		if ($next !== null && $entity->getSortPosition() > $next->getSortPosition()) {
			$position = $next->getSortPosition();
		} elseif ($previous !== null && $entity->getSortPosition() < $previous->getSortPosition()) {
			$position = $previous->getSortPosition();
		}

		$entity->sort($position);
	}

}
