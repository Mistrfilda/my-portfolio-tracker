<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

trait UpdatedAt
{

	#[ORM\Column(type:'datetime_immutable')]
	private ImmutableDateTime $updatedAt;

	public function getUpdatedAt(): ImmutableDateTime
	{
		return $this->updatedAt;
	}

}
