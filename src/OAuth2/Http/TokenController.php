<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\OAuth2\OAuthNotConfiguredException;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Token\OAuthTokenContext;
use App\OAuth2\Token\OAuthTokenStore;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TokenController
{

	public function __construct(
		private OAuthServerFactory $servers,
		private OAuthTokenStore $tokens,
		private OAuthTokenContext $context,
		private OAuthHttpGuard $guard,
		private OAuthClientAuthentication $clients,
		private OAuthResponses $responses,
	)
	{
	}

	public function token(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->context->reset();
		try {
			$this->guard->check($request, 'token');
			$client = $this->clients->authenticate($request);
			$this->context->clientId = $client->getIdentifier();
			$server = $this->servers->createAuthorizationServer();
			return $this->responses->noStore($this->tokens->transaction(
				function () use ($client, $server, $request, $response): ResponseInterface {
					$this->tokens->lockClient($client->getIdentifier());
					$this->clients->authenticate($request);
					return $server->respondToAccessTokenRequest($request, $response);
				},
			));
		} catch (OAuthServerException $e) {
			// Persist replay revocation after the failed issuance transaction rolled back.
			$this->tokens->revokeCompromisedFamily();
			return $this->responses->noStore($e->generateHttpResponse($response));
		} catch (OAuthNotConfiguredException) {
			return $this->responses->error($response, 'temporarily_unavailable', 'OAuth is not configured.', 503);
		} finally {
			$this->context->reset();
		}
	}

}
