<?php

declare(strict_types = 1);

namespace App\OAuth2\Token;

use App\Utils\TypeValidator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use LogicException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;

class OAuthTokenStore
{

	public function __construct(
		private Connection $connection,
		private DatetimeFactory $datetimeFactory,
		private OAuthTokenContext $context,
	)
	{
	}

	/**
	 * @template T
	 * @param callable(): T $operation
	 * @return T
	 */
	public function transaction(callable $operation): mixed
	{
		return $this->connection->transactional(static fn () => $operation());
	}

	public function lockClient(string $id): void
	{
		if (!$this->connection->isTransactionActive()) {
			throw new LogicException('Client locking requires a transaction.');
		}

		$this->connection->fetchOne('SELECT id FROM oauth_client WHERE id = ? FOR UPDATE', [$id]);
	}

	/** @param list<string> $scopes */
	public function persist(
		string $id,
		OAuthTokenType $type,
		string $clientId,
		string|null $userId,
		array $scopes,
		DateTimeImmutable $expiresAt,
		string|null $accessTokenId = null,
	): void
	{
		try {
			$this->connection->insert('oauth_token', [
				'id' => $id,
				'type' => $type->value,
				'client_id' => $clientId,
				'user_id' => $userId,
				'scopes' => Json::encode($scopes),
				'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
				'revoked' => 0,
				'family_id' => $this->context->familyId,
				'family_expires_at' => $this->context->familyExpiresAt?->format('Y-m-d H:i:s'),
				'access_token_id' => $accessTokenId,
			]);
		} catch (UniqueConstraintViolationException) {
			throw UniqueTokenIdentifierConstraintViolationException::create();
		}
	}

	public function find(string $id, OAuthTokenType $type, bool $lock = false): OAuthTokenRecord|null
	{
		if ($lock && !$this->connection->isTransactionActive()) {
			throw new LogicException('Grant token checks require a database transaction.');
		}

		$row = $this->connection->fetchAssociative(
			'SELECT * FROM oauth_token WHERE id = ? AND type = ?' . ($lock ? ' FOR UPDATE' : ''),
			[$id, $type->value],
		);
		if ($row === false) {
			return null;
		}

		$scopes = TypeValidator::validateArray(Json::decode(TypeValidator::validateString($row['scopes']), true));
		return new OAuthTokenRecord(
			TypeValidator::validateString($row['id']),
			$type,
			TypeValidator::validateString($row['client_id']),
			TypeValidator::validateNullableString($row['user_id']),
			array_values(array_map(TypeValidator::validateString(...), $scopes)),
			new ImmutableDateTime(TypeValidator::validateString($row['expires_at'])),
			TypeValidator::validateBool($row['revoked']),
			TypeValidator::validateNullableString($row['family_id']),
			$row['family_expires_at'] === null ? null : new ImmutableDateTime(
				TypeValidator::validateString($row['family_expires_at']),
			),
			TypeValidator::validateNullableString($row['access_token_id']),
		);
	}

	public function isRevoked(string $id, OAuthTokenType $type): bool
	{
		$record = $this->find($id, $type, $type !== OAuthTokenType::ACCESS);
		if ($record === null) {
			return true;
		}

		if ($type !== OAuthTokenType::ACCESS && $record->getClientId() !== $this->context->clientId) {
			return true;
		}

		if ($type !== OAuthTokenType::ACCESS && $record->isRevoked()) {
			$this->context->compromisedFamily = $record->getFamilyId();
		}

		$now = $this->datetimeFactory->createNow();
		if (
			$record->isRevoked()
			|| $record->getExpiresAt() <= $now
			|| (
				$record->getFamilyExpiresAt() !== null
				&& $record->getFamilyExpiresAt() <= $now
			)
		) {
			return true;
		}

		$active = $this->connection->fetchOne('SELECT active FROM oauth_client WHERE id = ?', [$record->getClientId()]);
		if ($active === false || !TypeValidator::validateBool($active)) {
			return true;
		}

		if ($type !== OAuthTokenType::ACCESS) {
			$this->context->familyId = $record->getFamilyId();
			$this->context->familyExpiresAt = $record->getFamilyExpiresAt();
		}

		return false;
	}

	public function revoke(string $id, OAuthTokenType $type): void
	{
		$this->connection->update('oauth_token', ['revoked' => 1], ['id' => $id, 'type' => $type->value]);
	}

	public function revokeFamily(string $familyId): void
	{
		$this->connection->update('oauth_token', ['revoked' => 1], ['family_id' => $familyId]);
	}

	public function revokeClient(string $clientId): void
	{
		$this->connection->update('oauth_token', ['revoked' => 1], ['client_id' => $clientId]);
	}

	public function revokeAuthorization(OAuthTokenRecord $record): void
	{
		if ($record->getFamilyId() !== null) {
			$this->revokeFamily($record->getFamilyId());
		} else {
			$this->revoke($record->getId(), $record->getType());
		}
	}

	public function revokeCompromisedFamily(): void
	{
		$family = $this->context->compromisedFamily;
		$client = $this->context->clientId;
		if ($family !== null && $client !== null) {
			$this->transaction(function () use ($family, $client): void {
				$this->lockClient($client);
				$this->revokeFamily($family);
			});
		}
	}

	public function cleanup(): int
	{
		// Retain consumed codes/refresh tokens until their family expires to detect replay.
		return TypeValidator::validateInt($this->connection->executeStatement(
			'DELETE FROM oauth_token WHERE expires_at < ? AND (family_expires_at IS NULL OR family_expires_at < ?)',
			[$this->datetimeFactory->createNow()->format('Y-m-d H:i:s'), $this->datetimeFactory->createNow()->format(
				'Y-m-d H:i:s',
			)],
		));
	}

}
