<?php

declare(strict_types = 1);

namespace App\Api;

use App\Admin\CurrentAppAdminGetter;
use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Http\OAuthPublicOriginResolver;
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

	/** @param list<OAuthScope> $alternativeScopes */
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
		private array $alternativeScopes = [],
		private string|null $resourceMetadataPath = null,
		private OAuthPublicOriginResolver|null $originResolver = null,
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

				return $this->unauthorized($request);
			}

			if (
				count($request->getHeader('Authorization')) !== 1
				|| preg_match(
					'/^Bearer [^\s,]+$/iD',
					$request->getHeaderLine('Authorization'),
				) !== 1
			) {
				return $this->unauthorized($request);
			}

			$request = $this->servers->createResourceServer()->validateAuthenticatedRequest($request);
			$id = $request->getAttribute('oauth_access_token_id');
			$record = is_string($id) ? $this->tokens->find($id, OAuthTokenType::ACCESS) : null;
			if ($record === null) {
				return $this->unauthorized($request);
			}

			$client = $this->clients->getClientEntity($record->getClientId());
			if ($client === null) {
				return $this->unauthorized($request);
			}

			$acceptedScopes = array_map(
				static fn (OAuthScope $scope): string => $scope->value,
				[$this->scope, ...$this->alternativeScopes],
			);
			if (array_intersect($acceptedScopes, $record->getScopes(), $client->getScopes()) === []) {
				return $this->responses->error(
					new Response(),
					'insufficient_scope',
					'The token does not allow this operation.',
					403,
				)
					->withHeader(
						'WWW-Authenticate',
						$this->buildAuthenticateHeader('insufficient_scope', $request, includeScope: true),
					);
			}

			// League uses the client ID as JWT sub for client credentials; use persisted user_id.
			$user = $record->getUserId() === null ? null : $this->users->findActive($record->getUserId());
			if ($record->getUserId() !== null && $user === null) {
				return $this->unauthorized($request);
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
			return $this->unauthorized($request);
		} catch (OAuthNotConfiguredException) {
			return $this->responses->error(new Response(), 'temporarily_unavailable', 'OAuth is not configured.', 503);
		} finally {
			$this->currentUser->endApiRequest();
		}
	}

	private function unauthorized(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->error(new Response(), 'invalid_token', 'A valid access token is required.', 401)
			->withHeader(
				'WWW-Authenticate',
				$this->buildAuthenticateHeader(
					'invalid_token',
					$request,
					includeScope: $this->resourceMetadataPath !== null,
				),
			);
	}

	private function buildAuthenticateHeader(
		string $error,
		ServerRequestInterface $request,
		bool $includeScope,
	): string
	{
		$parts = ['error="' . $error . '"'];
		if ($this->resourceMetadataPath !== null) {
			$resolver = $this->originResolver ?? new OAuthPublicOriginResolver();
			$parts[] = 'resource_metadata="' . $resolver->resolve($request) . $this->resourceMetadataPath . '"';
		}

		if ($includeScope) {
			$parts[] = 'scope="' . $this->scope->value . '"';
		}

		return 'Bearer ' . implode(', ', $parts);
	}

}
