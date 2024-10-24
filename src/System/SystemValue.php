<?php

declare(strict_types = 1);

namespace App\System;

use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('system_value')]
class SystemValue implements Entity
{

	use Identifier;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING, enumType: SystemValueEnum::class, unique: true)]
	private SystemValueEnum $systemValueEnum;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $datetimeValue;

	#[ORM\Column(type: Types::INTEGER, nullable: true)]
	private int|null $intValue;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stringValue;

	public function __construct(
		SystemValueEnum $systemValueEnum,
		ImmutableDateTime|null $datetimeValue,
		int|null $intValue,
		string|null $stringValue,
		ImmutableDateTime $now,
	)
	{
		$this->systemValueEnum = $systemValueEnum;
		$this->datetimeValue = $datetimeValue;
		$this->intValue = $intValue;
		$this->stringValue = $stringValue;
		$this->updatedAt = $now;
	}

	public function update(
		ImmutableDateTime $now,
		ImmutableDateTime|null $datetimeValue,
		int|null $intValue,
		string|null $stringValue,
	): void
	{
		$this->updatedAt = $now;
		$this->datetimeValue = $datetimeValue;
		$this->intValue = $intValue;
		$this->stringValue = $stringValue;
	}

	public function getSystemValueEnum(): SystemValueEnum
	{
		return $this->systemValueEnum;
	}

	public function getDatetimeValue(): ImmutableDateTime|null
	{
		return $this->datetimeValue;
	}

	public function getIntValue(): int|null
	{
		return $this->intValue;
	}

	public function getStringValue(): string|null
	{
		return $this->stringValue;
	}

}
