<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Row;

use App\Doctrine\Entity;

interface RowRenderer
{

	public function getRowClasses(Entity $entity, int $currentIndex): string;

}
