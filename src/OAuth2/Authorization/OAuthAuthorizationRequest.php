<?php

declare(strict_types = 1);

namespace App\OAuth2\Authorization;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('oauth_authorization_request')]
#[ORM\Index(columns: ['expires_at'], name: 'oauth_authorization_expiry_idx')]
class OAuthAuthorizationRequest
{

	#[ORM\Id]
	#[ORM\Column(type: Types::STRING, length: 64)]
	private string $id;

	#[ORM\Column(name: 'csrf_hash', type: Types::STRING, length: 64)]
	private string $csrfHash;

	/** @var array<string, string> */
	#[ORM\Column(type: Types::JSON)]
	private array $parameters;

	#[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $expiresAt;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $consumed;

	/** @param array<string, string> $parameters */
	public function __construct(
		string $id,
		string $csrfHash,
		array $parameters,
		ImmutableDateTime $expiresAt,
		bool $consumed,
	)
	{
		$this->id = $id;
		$this->csrfHash = $csrfHash;
		$this->parameters = $parameters;
		$this->expiresAt = $expiresAt;
		$this->consumed = $consumed;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getCsrfHash(): string
	{
		return $this->csrfHash;
	}

	/** @return array<string, string> */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	public function getExpiresAt(): ImmutableDateTime
	{
		return $this->expiresAt;
	}

	public function isConsumed(): bool
	{
		return $this->consumed;
	}

}
