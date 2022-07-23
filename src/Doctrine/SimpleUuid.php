<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

trait SimpleUuid
{

	#[ORM\Id]
	#[ORM\Column(type: 'uuid', unique: true)]
	private UuidInterface $id;

	public function getId(): UuidInterface
	{
		return $this->id;
	}

}
