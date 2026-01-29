<?php

declare(strict_types = 1);

namespace App\Home\Device;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Home\Device\Record\HomeDeviceRecord;
use App\Home\Home;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid as RamseyUuid;

#[ORM\Entity]
#[ORM\Table('home_device')]
#[ORM\Index(fields: ['internalId'], name: 'internal_id_idx')]
class HomeDevice implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: Home::class, inversedBy: 'devices')]
	#[ORM\JoinColumn(nullable: false)]
	private Home $home;

	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $internalId;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, enumType: HomeDeviceType::class)]
	private HomeDeviceType $type;

	/** @var ArrayCollection<int, HomeDeviceRecord> */
	#[ORM\OneToMany(targetEntity: HomeDeviceRecord::class, mappedBy: 'homeDevice')]
	private Collection $records;

	public function __construct(
		Home $home,
		string $internalId,
		string $name,
		HomeDeviceType $type,
		ImmutableDateTime $now,
	)
	{
		$this->id = RamseyUuid::uuid4();
		$this->home = $home;
		$this->internalId = $internalId;
		$this->name = $name;
		$this->type = $type;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->records = new ArrayCollection();
	}

	public function update(
		string $internalId,
		string $name,
		HomeDeviceType $type,
		ImmutableDateTime $now,
	): void
	{
		$this->internalId = $internalId;
		$this->name = $name;
		$this->type = $type;
		$this->updatedAt = $now;
	}

	public function getHome(): Home
	{
		return $this->home;
	}

	public function getInternalId(): string
	{
		return $this->internalId;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getType(): HomeDeviceType
	{
		return $this->type;
	}

	/**
	 * @return Collection<int, HomeDeviceRecord>
	 */
	public function getRecords(): Collection
	{
		return $this->records;
	}

}
