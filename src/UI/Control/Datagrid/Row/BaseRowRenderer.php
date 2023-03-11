<?php

declare(strict_types = 1);

namespace App\UI\Control\Datagrid\Row;

use App\Doctrine\Entity;
use Closure;

class BaseRowRenderer implements RowRenderer
{

	public function __construct(private Closure $rowClassHandler)
	{
	}

	public function getRowClasses(Entity $entity, int $currentIndex): string
	{
		$classes = call_user_func($this->rowClassHandler, $entity);
		assert(is_string($classes));
		return $classes;
	}

}
