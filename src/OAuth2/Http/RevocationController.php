<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\OAuth2\OAuthKeys;
use App\OAuth2\OAuthNotConfiguredException;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Token\OAuthTokenStore;
use App\OAuth2\Token\OAuthTokenType;
use InvalidArgumentException;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RevocationController
{

	use CryptTrait;

	public function __construct(
		private OAuthKeys $keys,
		private OAuthServerFactory $servers,
		private OAuthTokenStore $tokens,
		private OAuthClientAuthentication $clients,
		private OAuthHttpGuard $guard,
		private OAuthResponses $responses,
	)
	{
	}

	public function revoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try {
			$this->guard->check($request, 'revoke');
			$client = $this->clients->authenticate($request);
			$body = $request->getParsedBody();
			$token = is_array($body) ? ($body['token'] ?? null) : null;
			if (!is_string($token) || $token === '') {
				throw OAuthServerException::invalidRequest('token');
			}

			$id = $this->decodeRefreshToken($token);
			$type = OAuthTokenType::REFRESH;
			if ($id === null) {
				$type = OAuthTokenType::ACCESS;
				try {
					$validated = $this->servers->createResourceServer()->validateAuthenticatedRequest(
						$request->withHeader('Authorization', 'Bearer ' . $token),
					);
					$value = $validated->getAttribute('oauth_access_token_id');
					$id = is_string($value) ? $value : null;
				} catch (OAuthServerException) {
					$id = null;
				}
			}

			if ($id !== null) {
				$this->tokens->transaction(function () use ($id, $type, $client, $request): void {
					$this->tokens->lockClient($client->getIdentifier());
					$this->clients->authenticate($request);
					$record = $this->tokens->find($id, $type);
					if ($record !== null && $record->getClientId() === $client->getIdentifier()) {
						$this->tokens->revokeAuthorization($record);
					}
				});
			}

			return $this->responses->noStore($response->withStatus(200));
		} catch (OAuthServerException $e) {
			return $this->responses->noStore($e->generateHttpResponse($response));
		} catch (OAuthNotConfiguredException) {
			return $this->responses->error($response, 'temporarily_unavailable', 'OAuth is not configured.', 503);
		}
	}

	private function decodeRefreshToken(string $token): string|null
	{
		$this->setEncryptionKey($this->keys->getEncryptionKey());
		try {
			$data = Json::decode($this->decrypt($token), true);
			return is_array($data) && isset($data['refresh_token_id']) && is_string(
				$data['refresh_token_id'],
			)
				? $data['refresh_token_id']
				: null;
		} catch (InvalidArgumentException | JsonException) {
			return null;
		}
	}

}
