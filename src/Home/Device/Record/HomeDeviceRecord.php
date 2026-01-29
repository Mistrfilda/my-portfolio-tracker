<?php

declare(strict_types = 1);

namespace App\Home\Device\Record;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Home\Device\HomeDevice;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid as RamseyUuid;

#[ORM\Entity]
#[ORM\Table('home_device_record')]
#[ORM\Index(fields: ['createdAt'], name: 'created_at_idx')]
class HomeDeviceRecord implements Entity
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: HomeDevice::class, inversedBy: 'records')]
	#[ORM\JoinColumn(nullable: false)]
	private HomeDevice $homeDevice;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: true)]
	private AppAdmin|null $createdBy;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stringValue;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $floatValue;

	#[ORM\Column(type: Types::STRING, enumType: HomeDeviceRecordUnit::class, nullable: true)]
	private HomeDeviceRecordUnit|null $unit;

	public function __construct(
		HomeDevice $homeDevice,
		AppAdmin|null $createdBy,
		string|null $stringValue,
		float|null $floatValue,
		HomeDeviceRecordUnit|null $unit,
		ImmutableDateTime $now,
	)
	{
		$this->id = RamseyUuid::uuid4();
		$this->homeDevice = $homeDevice;
		$this->createdBy = $createdBy;
		$this->stringValue = $stringValue;
		$this->floatValue = $floatValue;
		$this->unit = $unit;
		$this->createdAt = $now;
	}

	public function getHomeDevice(): HomeDevice
	{
		return $this->homeDevice;
	}

	public function getCreatedBy(): AppAdmin|null
	{
		return $this->createdBy;
	}

	public function getStringValue(): string|null
	{
		return $this->stringValue;
	}

	public function getFloatValue(): float|null
	{
		return $this->floatValue;
	}

	public function getUnit(): HomeDeviceRecordUnit|null
	{
		return $this->unit;
	}

}
