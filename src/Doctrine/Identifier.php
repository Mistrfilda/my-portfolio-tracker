<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait Identifier
{

	#[ORM\Id]
	#[ORM\Column(type: Types::INTEGER)]
	#[ORM\GeneratedValue]
	private int $id;

	public function getId(): int
	{
		return $this->id;
	}

}
