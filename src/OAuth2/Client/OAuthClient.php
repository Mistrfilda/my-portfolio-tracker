<?php

declare(strict_types = 1);

namespace App\OAuth2\Client;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use LogicException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('oauth_client')]
class OAuthClient implements ClientEntityInterface
{

	#[ORM\Id]
	#[ORM\Column(type: Types::STRING, length: 80)]
	private string $id;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $confidential;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $secretHash;

	/** @var list<string> */
	#[ORM\Column(type: Types::JSON)]
	private array $redirectUris;

	/** @var list<string> */
	#[ORM\Column(type: Types::JSON)]
	private array $scopes;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $createdAt;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $active;

	/**
	 * @param list<string> $redirectUris
	 * @param list<string> $scopes
	 */
	public function __construct(
		string $id,
		string $name,
		bool $confidential,
		string|null $secretHash,
		array $redirectUris,
		array $scopes,
		ImmutableDateTime $createdAt,
		bool $active = true,
	)
	{
		$this->id = $id;
		$this->name = $name;
		$this->confidential = $confidential;
		$this->secretHash = $secretHash;
		$this->redirectUris = $redirectUris;
		$this->scopes = $scopes;
		$this->createdAt = $createdAt;
		$this->active = $active;
	}

	public function getIdentifier(): string
	{
		if ($this->id === '') {
			throw new LogicException('OAuth client identifiers must not be empty.');
		}

		return $this->id;
	}

	public function getName(): string
	{
		return $this->name;
	}

	/** @return list<string> */
	public function getRedirectUri(): array
	{
		return $this->redirectUris;
	}

	public function isConfidential(): bool
	{
		return $this->confidential;
	}

	public function getSecretHash(): string|null
	{
		return $this->secretHash;
	}

	/** @return list<string> */
	public function getScopes(): array
	{
		return $this->scopes;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

	public function disable(): void
	{
		$this->active = false;
	}

	public function changeSecretHash(string $hash): void
	{
		$this->secretHash = $hash;
	}

	public function supportsGrantType(string $grantType): bool
	{
		return $this->active && match ($grantType) {
			'client_credentials' => $this->confidential,
			'authorization_code', 'refresh_token' => $this->redirectUris !== [],
			default => false,
		};
	}

}
