<?php

declare(strict_types = 1);

namespace App\Admin;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\UpdatedAt;
use App\Doctrine\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('app_admin')]
class AppAdmin implements Entity
{

	use Uuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $username;

	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $email;

	#[ORM\Column(type: Types::STRING)]
	private string $password;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $forceNewPassword;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $sysAdmin;

	public function __construct(
		string $name,
		string $username,
		string $email,
		string $password,
		ImmutableDateTime $now,
		bool $forceNewPassword,
		bool $sysAdmin,
	)
	{
		$this->name = $name;
		$this->username = $username;
		$this->email = $email;
		$this->password = $password;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->forceNewPassword = $forceNewPassword;
		$this->sysAdmin = $sysAdmin;
	}

	public function update(
		string $name,
		string $email,
		string|null $password,
		ImmutableDateTime $now,
		bool $forceNewPassword,
		bool $sysAdmin,
	): void
	{
		$this->name = $name;
		$this->email = $email;
		if ($password !== null) {
			$this->password = $password;
		}

		$this->forceNewPassword = $forceNewPassword;
		$this->sysAdmin = $sysAdmin;
		$this->updatedAt = $now;
	}

	public function forceNewPassword(): void
	{
		$this->forceNewPassword = true;
	}

	public function changePassword(string $password, ImmutableDateTime $now): void
	{
		$this->password = $password;
		$this->updatedAt = $now;
		$this->forceNewPassword = false;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getUsername(): string
	{
		return $this->username;
	}

	public function getPassword(): string
	{
		return $this->password;
	}

	public function getEmail(): string
	{
		return $this->email;
	}

	public function isNewPasswordForced(): bool
	{
		return $this->forceNewPassword;
	}

	public function isSysAdmin(): bool
	{
		return $this->sysAdmin;
	}

}
