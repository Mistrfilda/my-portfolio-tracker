<?php

declare(strict_types = 1);

namespace App\OAuth2\Token;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('oauth_token')]
#[ORM\Index(columns: ['client_id'], name: 'oauth_token_client_idx')]
#[ORM\Index(columns: ['family_id'], name: 'oauth_token_family_idx')]
#[ORM\Index(columns: ['expires_at'], name: 'oauth_token_expiry_idx')]
class OAuthTokenRecord
{

	#[ORM\Id]
	#[ORM\Column(type: Types::STRING, length: 80)]
	private string $id;

	#[ORM\Column(type: Types::STRING, length: 16, enumType: OAuthTokenType::class)]
	private OAuthTokenType $type;

	#[ORM\Column(name: 'client_id', type: Types::STRING, length: 80)]
	private string $clientId;

	#[ORM\Column(name: 'user_id', type: Types::STRING, length: 80, nullable: true)]
	private string|null $userId;

	/** @var list<string> */
	#[ORM\Column(type: Types::JSON)]
	private array $scopes;

	#[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $expiresAt;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $revoked;

	#[ORM\Column(name: 'family_id', type: Types::STRING, length: 64, nullable: true)]
	private string|null $familyId;

	#[ORM\Column(name: 'family_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $familyExpiresAt;

	#[ORM\Column(name: 'access_token_id', type: Types::STRING, length: 80, nullable: true)]
	private string|null $accessTokenId;

	/** @param list<string> $scopes */
	public function __construct(
		string $id,
		OAuthTokenType $type,
		string $clientId,
		string|null $userId,
		array $scopes,
		ImmutableDateTime $expiresAt,
		bool $revoked,
		string|null $familyId,
		ImmutableDateTime|null $familyExpiresAt,
		string|null $accessTokenId,
	)
	{
		$this->id = $id;
		$this->type = $type;
		$this->clientId = $clientId;
		$this->userId = $userId;
		$this->scopes = $scopes;
		$this->expiresAt = $expiresAt;
		$this->revoked = $revoked;
		$this->familyId = $familyId;
		$this->familyExpiresAt = $familyExpiresAt;
		$this->accessTokenId = $accessTokenId;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getType(): OAuthTokenType
	{
		return $this->type;
	}

	public function getClientId(): string
	{
		return $this->clientId;
	}

	public function getUserId(): string|null
	{
		return $this->userId;
	}

	/** @return list<string> */
	public function getScopes(): array
	{
		return $this->scopes;
	}

	public function getExpiresAt(): ImmutableDateTime
	{
		return $this->expiresAt;
	}

	public function isRevoked(): bool
	{
		return $this->revoked;
	}

	public function getFamilyId(): string|null
	{
		return $this->familyId;
	}

	public function getFamilyExpiresAt(): ImmutableDateTime|null
	{
		return $this->familyExpiresAt;
	}

	public function getAccessTokenId(): string|null
	{
		return $this->accessTokenId;
	}

}
