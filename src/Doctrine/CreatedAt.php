<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

trait CreatedAt
{

	#[ORM\Column(type:'datetime_immutable')]
	private ImmutableDateTime $createdAt;

	public function getCreatedAt(): ImmutableDateTime
	{
		return $this->createdAt;
	}

}
