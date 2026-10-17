<?php

declare(strict_types = 1);

namespace App\OAuth2\Client;

use App\OAuth2\OAuthScope;
use App\OAuth2\Token\OAuthTokenStore;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use const PASSWORD_DEFAULT;

class OAuthClientFacade
{

	public function __construct(
		private OAuthClientRepository $clients,
		private OAuthTokenStore $tokens,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @param list<string> $scopes
	 * @param list<string> $redirectUris
	 */
	public function create(string $name, bool $confidential, array $scopes, array $redirectUris): OAuthClientCredentials
	{
		if (trim($name) === '' || strlen($name) > 255 || $scopes === []) {
			throw new InvalidArgumentException('A client name and at least one scope are required.');
		}

		foreach ($scopes as $scope) {
			if (OAuthScope::tryFrom($scope) === null) {
				throw new InvalidArgumentException('Unknown OAuth scope: ' . $scope);
			}
		}

		if (!$confidential && $redirectUris === []) {
			throw new InvalidArgumentException('Public clients require a registered redirect URI.');
		}

		foreach ($redirectUris as $uri) {
			$this->validateRedirectUri($uri);
		}

		$secret = $confidential ? bin2hex(random_bytes(32)) : null;
		$client = new OAuthClient(
			bin2hex(random_bytes(20)),
			trim($name),
			$confidential,
			$secret === null ? null : password_hash($secret, PASSWORD_DEFAULT),
			array_values(array_unique($redirectUris)),
			array_values(array_unique($scopes)),
			$this->datetimeFactory->createNow(),
		);
		$this->clients->save($client);
		return new OAuthClientCredentials($client, $secret);
	}

	public function rotateSecret(string $id): OAuthClientCredentials
	{
		return $this->tokens->transaction(function () use ($id): OAuthClientCredentials {
			$this->tokens->lockClient($id);
			$client = $this->clients->getClientEntity($id);
			if ($client === null || !$client->isConfidential()) {
				throw new InvalidArgumentException('An active confidential client is required.');
			}

			$secret = bin2hex(random_bytes(32));
			$client->changeSecretHash(password_hash($secret, PASSWORD_DEFAULT));
			$this->clients->save($client);
			$this->tokens->revokeClient($id);
			return new OAuthClientCredentials($client, $secret);
		});
	}

	public function disable(string $id): void
	{
		$this->tokens->transaction(function () use ($id): void {
			$this->tokens->lockClient($id);
			$client = $this->clients->find($id);
			if ($client === null) {
				throw new InvalidArgumentException('OAuth client not found.');
			}

			$client->disable();
			$this->clients->save($client);
			$this->tokens->revokeClient($id);
		});
	}

	private function validateRedirectUri(string $uri): void
	{
		$parts = parse_url($uri);
		if (
			$parts === false || !isset($parts['scheme'])
			|| isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])
			|| str_contains($uri, '*') || preg_match('/[\s\x00-\x1f]/', $uri) === 1
		) {
			throw new InvalidArgumentException('Invalid redirect URI.');
		}

		$scheme = strtolower($parts['scheme']);
		if ($scheme === 'https' && isset($parts['host'])) {
			return;
		}

		if ($scheme === 'http' && in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true)) {
			return;
		}

		if (!in_array($scheme, ['http', 'https'], true) && str_contains($scheme, '.') && isset($parts['path'])) {
			return;
		}

		throw new InvalidArgumentException(
			'Use HTTPS, a loopback HTTP URI, or a reverse-domain native application scheme.',
		);
	}

}
