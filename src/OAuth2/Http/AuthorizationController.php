<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\OAuth2\Authorization\OAuthAuthorizationStore;
use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\OAuthNotConfiguredException;
use App\OAuth2\OAuthScope;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Protocol\ScopeRepository;
use App\OAuth2\Protocol\UserEntity;
use App\OAuth2\Token\OAuthTokenContext;
use App\OAuth2\Token\OAuthTokenStore;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthorizationController
{

	public function __construct(
		private OAuthServerFactory $servers,
		private OAuthAuthorizationStore $authorizations,
		private OAuthUserAuthenticator $users,
		private ScopeRepository $scopes,
		private OAuthTokenStore $tokens,
		private OAuthTokenContext $context,
		private OAuthHttpGuard $guard,
		private OAuthResponses $responses,
		private LatteFactory $latteFactory,
	)
	{
	}

	public function authorize(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->context->reset();
		try {
			$this->guard->check(
				$request,
				'authorize:' . $request->getMethod(),
				$request->getMethod() === 'POST' ? 10 : 30,
			);
			if ($request->getMethod() === 'GET') {
				return $this->render($request, $response, $this->parameters($request));
			}

			$this->checkOrigin($request);
			$body = $request->getParsedBody();
			$body = is_array($body) ? $body : [];
			$transaction = $body['transaction'] ?? null;
			if (!is_string($transaction)) {
				throw OAuthServerException::invalidRequest('transaction');
			}

			$cookie = $request->getCookieParams()['oauth_authorization'] ?? '';
			$parameters = $this->authorizations->consume($transaction, is_string($cookie) ? $cookie : '');
			$auth = $this->validate($request->withQueryParams($parameters));
			if (($body['decision'] ?? null) === 'deny') {
				$uri = $auth->getRedirectUri();
				assert($uri !== null);
				$uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query(
					['error' => 'access_denied', 'state' => $auth->getState()],
				);
				return $this->secure($response->withStatus(303)->withHeader('Location', $uri));
			}

			if (($body['decision'] ?? null) !== 'approve') {
				throw OAuthServerException::invalidRequest('decision');
			}

			$username = $body['username'] ?? null;
			$password = $body['password'] ?? null;
			$user = null;
			if (is_string($username) && is_string($password) && strlen($username) <= 255 && strlen($password) <= 4096) {
				$user = $this->users->authenticate($username, $password);
			}

			if ($user === null) {
				return $this->render(
					$request,
					$response->withStatus(401),
					$parameters,
					'Přihlášení se nezdařilo. Zkontrolujte údaje; případnou povinnou změnu hesla dokončete ve webové aplikaci.',
				);
			}

			$completed = $this->tokens->transaction(
				function () use ($auth, $request, $parameters, $user, $response): ResponseInterface {
					$this->tokens->lockClient($auth->getClient()->getIdentifier());
					$approved = $this->validate($request->withQueryParams($parameters));
					$approved->setUser(new UserEntity($user->getId()->toString()));
					$approved->setAuthorizationApproved(true);
					$server = $this->servers->createAuthorizationServer();
					return $server->completeAuthorizationRequest($approved, $response)->withStatus(303);
				},
			);
			return $this->secure($completed);
		} catch (OAuthServerException $e) {
			// Never redirect invalid authorization requests to an untrusted URI.
			return $this->secure(
				$this->responses->error($response, $e->getErrorType(), $e->getMessage(), $e->getHttpStatusCode()),
			);
		} catch (OAuthNotConfiguredException) {
			return $this->secure(
				$this->responses->error($response, 'temporarily_unavailable', 'OAuth is not configured.', 503),
			);
		} finally {
			$this->context->reset();
		}
	}

	/** @return array<string, string> */
	private function parameters(ServerRequestInterface $request): array
	{
		$parameters = [];
		foreach (['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'code_challenge', 'code_challenge_method'] as $key) {
			$value = $request->getQueryParams()[$key] ?? null;
			if (!is_string($value) || $value === '' || strlen($value) > 2048) {
				throw OAuthServerException::invalidRequest($key);
			}

			$parameters[$key] = $value;
		}

		return $parameters;
	}

	private function validate(ServerRequestInterface $request): AuthorizationRequestInterface
	{
		$parameters = $this->parameters($request);
		$isS256Challenge = preg_match('/^[A-Za-z0-9_-]{43}$/D', $parameters['code_challenge']) === 1;
		if ($parameters['code_challenge_method'] !== 'S256' || !$isS256Challenge) {
			throw OAuthServerException::invalidRequest('code_challenge', 'S256 PKCE is required.');
		}

		$auth = $this->servers->createAuthorizationServer()->validateAuthorizationRequest($request);
		$this->scopes->finalizeScopes($auth->getScopes(), 'authorization_code', $auth->getClient());
		return $auth;
	}

	/** @param array<string, string> $parameters */
	private function render(
		ServerRequestInterface $request,
		ResponseInterface $response,
		array $parameters,
		string|null $error = null,
	): ResponseInterface
	{
		$auth = $this->validate($request->withQueryParams($parameters));
		$csrf = bin2hex(random_bytes(32));
		$transaction = $this->authorizations->create($parameters, $csrf);
		$labels = [];
		foreach ($auth->getScopes() as $scope) {
			$labels[] = OAuthScope::from($scope->getIdentifier())->getLabel();
		}

		$path = $request->getUri()->getPath();
		$page = new AuthorizationPage($auth->getClient()->getName(), $labels, $transaction, $path, $error);
		$response->getBody()->write($this->latteFactory->create()->renderToString(__DIR__ . '/authorize.latte', $page));
		$cookie = 'oauth_authorization=' . $csrf . '; Path=' . $path . '; Max-Age=600; HttpOnly; SameSite=Strict';
		if ($request->getUri()->getScheme() === 'https') {
			$cookie .= '; Secure';
		}

		return $this->secure(
			$response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Set-Cookie', $cookie),
		);
	}

	private function checkOrigin(ServerRequestInterface $request): void
	{
		$origin = $request->getHeaderLine('Origin');
		$expected = $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority();
		if (($origin !== '' && $origin !== $expected) || $request->getHeaderLine('Sec-Fetch-Site') === 'cross-site') {
			throw new OAuthServerException('Cross-origin authorization submission denied.', 0, 'access_denied', 403);
		}
	}

	private function secure(ResponseInterface $response): ResponseInterface
	{
		// form-action is omitted because browsers can apply it to the OAuth callback redirect.
		return $this->responses->noStore($response)
			->withHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'")
			->withHeader('Referrer-Policy', 'no-referrer')
			->withHeader('X-Frame-Options', 'DENY')
			->withHeader('X-Content-Type-Options', 'nosniff');
	}

}
