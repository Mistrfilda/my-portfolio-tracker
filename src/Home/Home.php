<?php

declare(strict_types = 1);

namespace App\Home;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Home\Device\HomeDevice;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid as RamseyUuid;

#[ORM\Entity]
#[ORM\Table('home')]
class Home implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	/** @var ArrayCollection<int, HomeDevice> */
	#[ORM\OneToMany(targetEntity: HomeDevice::class, mappedBy: 'home')]
	private Collection $devices;

	public function __construct(string $name, ImmutableDateTime $now,)
	{
		$this->id = RamseyUuid::uuid4();
		$this->name = $name;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->devices = new ArrayCollection();
	}

	public function update(string $name, ImmutableDateTime $now,): void
	{
		$this->name = $name;
		$this->updatedAt = $now;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Collection<int, HomeDevice>
	 */
	public function getDevices(): Collection
	{
		return $this->devices;
	}

}
