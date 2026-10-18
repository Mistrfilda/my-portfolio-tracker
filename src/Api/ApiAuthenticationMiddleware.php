<?php

declare(strict_types = 1);

namespace App\Api;

use App\Admin\CurrentAppAdminGetter;
use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Http\OAuthResponses;
use App\OAuth2\OAuthNotConfiguredException;
use App\OAuth2\OAuthScope;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Token\OAuthTokenStore;
use App\OAuth2\Token\OAuthTokenType;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Psr7\Response;

class ApiAuthenticationMiddleware implements MiddlewareInterface
{

	public function __construct(
		private OAuthScope $scope,
		private bool $allowLegacy,
		private MiddlewareInterface $apiKeys,
		private OAuthServerFactory $servers,
		private OAuthTokenStore $tokens,
		private OAuthClientRepository $clients,
		private OAuthUserAuthenticator $users,
		private CurrentAppAdminGetter $currentUser,
		private OAuthResponses $responses,
		private bool $requireUser = false,
	)
	{
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$this->currentUser->beginApiRequest();
		try {
			if ($request->hasHeader('Authorization') && $request->hasHeader('X-Api-Key')) {
				return $this->responses->error(
					new Response(),
					'invalid_request',
					'Use a single authentication method.',
					400,
				);
			}

			if (!$request->hasHeader('Authorization')) {
				if ($this->allowLegacy && $request->hasHeader('X-Api-Key')) {
					return $this->responses->noStore($this->apiKeys->process($request, $handler));
				}

				return $this->unauthorized();
			}

			if (
				count($request->getHeader('Authorization')) !== 1
				|| preg_match(
					'/^Bearer [^\s,]+$/iD',
					$request->getHeaderLine('Authorization'),
				) !== 1
			) {
				return $this->unauthorized();
			}

			$request = $this->servers->createResourceServer()->validateAuthenticatedRequest($request);
			$id = $request->getAttribute('oauth_access_token_id');
			$record = is_string($id) ? $this->tokens->find($id, OAuthTokenType::ACCESS) : null;
			if ($record === null) {
				return $this->unauthorized();
			}

			$client = $this->clients->getClientEntity($record->getClientId());
			if ($client === null) {
				return $this->unauthorized();
			}

			if (
				!in_array($this->scope->value, $record->getScopes(), true)
				|| !in_array($this->scope->value, $client->getScopes(), true)
			) {
				return $this->responses->error(
					new Response(),
					'insufficient_scope',
					'The token does not allow this operation.',
					403,
				)
					->withHeader(
						'WWW-Authenticate',
						'Bearer error="insufficient_scope", scope="' . $this->scope->value . '"',
					);
			}

			// League uses the client ID as JWT sub for client credentials; use persisted user_id.
			$user = $record->getUserId() === null ? null : $this->users->findActive($record->getUserId());
			if ($record->getUserId() !== null && $user === null) {
				return $this->unauthorized();
			}

			if ($this->requireUser && $user === null) {
				return $this->responses->error(
					new Response(),
					'access_denied',
					'This operation requires user authorization.',
					403,
				);
			}

			if ($user !== null) {
				$this->currentUser->setApiAppAdmin($user);
			}

			return $this->responses->noStore(
				$handler->handle(
					$request->withAttribute(
						'api_principal',
						new ApiPrincipal($record->getClientId(), $user, $record->getScopes()),
					),
				),
			);
		} catch (OAuthServerException | HttpUnauthorizedException) {
			return $this->unauthorized();
		} catch (OAuthNotConfiguredException) {
			return $this->responses->error(new Response(), 'temporarily_unavailable', 'OAuth is not configured.', 503);
		} finally {
			$this->currentUser->endApiRequest();
		}
	}

	private function unauthorized(): ResponseInterface
	{
		return $this->responses->error(new Response(), 'invalid_token', 'A valid access token is required.', 401)
			->withHeader('WWW-Authenticate', 'Bearer error="invalid_token"');
	}

}
