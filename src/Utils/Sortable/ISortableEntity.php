<?php

declare(strict_types = 1);

namespace App\Utils\Sortable;

interface ISortableEntity
{

	public function getSortPosition(): int;

	public function sort(int $sortPosition): void;

	public function getSortingGroup(): string;

}
