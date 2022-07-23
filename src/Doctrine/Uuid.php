<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

trait Uuid
{

	#[ORM\Id]
	#[ORM\Column(type: 'uuid', unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
	private UuidInterface $id;

	public function getId(): UuidInterface
	{
		return $this->id;
	}

	public function getIdToString(): string
	{
		return $this->id->toString();
	}

}
