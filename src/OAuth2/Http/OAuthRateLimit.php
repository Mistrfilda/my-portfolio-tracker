<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('oauth_rate_limit')]
class OAuthRateLimit
{

	#[ORM\Id]
	#[ORM\Column(type: Types::STRING, length: 64)]
	private string $id;

	#[ORM\Column(type: Types::INTEGER)]
	private int $attempts;

	#[ORM\Column(name: 'reset_at', type: Types::INTEGER)]
	private int $resetAt;

	public function __construct(string $id, int $attempts, int $resetAt,)
	{
		$this->id = $id;
		$this->attempts = $attempts;
		$this->resetAt = $resetAt;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getAttempts(): int
	{
		return $this->attempts;
	}

	public function getResetAt(): int
	{
		return $this->resetAt;
	}

}
