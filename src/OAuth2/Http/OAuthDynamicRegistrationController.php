<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\OAuthScope;
use App\Utils\TypeValidator;
use InvalidArgumentException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OAuthDynamicRegistrationController
{

	private const int RATE_LIMIT_PER_MINUTE = 10;

	private const int MAX_REDIRECT_URIS = 10;

	private const array ALLOWED_GRANT_TYPES = ['authorization_code', 'refresh_token'];

	public function __construct(
		private OAuthClientFacade $clientFacade,
		private OAuthRateLimiter $rateLimiter,
		private OAuthResponses $responses,
		private bool $enabled = true,
	)
	{
	}

	public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->enabled) {
			return $this->responses->error(
				$response,
				'registration_disabled',
				'Dynamic client registration is disabled.',
				403,
			);
		}

		$ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
		if (!is_string($ip) || !$this->rateLimiter->allow('register:' . $ip, self::RATE_LIMIT_PER_MINUTE)) {
			return $this->responses->error(
				$response,
				'temporarily_unavailable',
				'Too many requests. Try again later.',
				429,
			);
		}

		$contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
		if ($contentType !== 'application/json') {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'Use application/json Content-Type.',
				400,
			);
		}

		try {
			$decoded = Json::decode((string) $request->getBody(), forceArrays: true);
		} catch (JsonException) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'Request body must be valid JSON.',
				400,
			);
		}

		if (!is_array($decoded)) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'Request body must be a JSON object.',
				400,
			);
		}

		$clientNameResult = $this->extractClientName($decoded);
		if ($clientNameResult === null) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'client_name must be a non-empty string up to 255 characters.',
				400,
			);
		}

		$redirectUris = $this->extractLoopbackRedirectUris($decoded);
		if ($redirectUris === null) {
			return $this->responses->error(
				$response,
				'invalid_redirect_uri',
				'redirect_uris must be a non-empty list of loopback HTTP URIs (127.0.0.1, localhost, or [::1]).',
				400,
			);
		}

		if (!$this->validateAuthMethod($decoded)) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'Only public PKCE clients with token_endpoint_auth_method="none" are supported.',
				400,
			);
		}

		$grantTypes = $this->extractGrantTypes($decoded);
		if ($grantTypes === null) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'grant_types must include authorization_code and may only contain authorization_code and refresh_token.',
				400,
			);
		}

		$responseTypes = $this->extractResponseTypes($decoded);
		if ($responseTypes === null) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'response_types may only contain "code".',
				400,
			);
		}

		if (!$this->validateScope($decoded)) {
			return $this->responses->error(
				$response,
				'invalid_client_metadata',
				'Dynamic client registration only permits the "mcp:read" scope.',
				400,
			);
		}

		try {
			$credentials = $this->clientFacade->create(
				$clientNameResult,
				false,
				[OAuthScope::MCP_READ->value],
				$redirectUris,
			);
		} catch (InvalidArgumentException $e) {
			return $this->responses->error($response, 'invalid_client_metadata', $e->getMessage(), 400);
		}

		$payload = [
			'client_id' => $credentials->client->getIdentifier(),
			'client_name' => $credentials->client->getName(),
			'redirect_uris' => $credentials->client->getRedirectUri(),
			'grant_types' => $grantTypes,
			'response_types' => $responseTypes,
			'token_endpoint_auth_method' => 'none',
			'scope' => OAuthScope::MCP_READ->value,
			'client_id_issued_at' => $credentials->client->getCreatedAt()->getTimestamp(),
		];

		$response->getBody()->write(Json::encode($payload));
		return $this->responses->noStore(
			$response->withStatus(201)->withHeader('Content-Type', 'application/json'),
		);
	}

	/** @param array<mixed> $payload */
	private function extractClientName(array $payload): string|null
	{
		if (!array_key_exists('client_name', $payload)) {
			return 'MCP Client';
		}

		if (!is_string($payload['client_name'])) {
			return null;
		}

		$name = trim(TypeValidator::validateString($payload['client_name']));
		if ($name === '' || strlen($name) > 255) {
			return null;
		}

		return $name;
	}

	/**
	 * @param array<mixed> $payload
	 * @return list<string>|null
	 */
	private function extractLoopbackRedirectUris(array $payload): array|null
	{
		$raw = $payload['redirect_uris'] ?? null;
		if (!is_array($raw) || $raw === [] || count($raw) > self::MAX_REDIRECT_URIS || !array_is_list($raw)) {
			return null;
		}

		$uris = [];
		foreach ($raw as $item) {
			if (!is_string($item) || strlen($item) > 2048 || !$this->clientFacade->isLoopbackRedirectUri($item)) {
				return null;
			}

			$uris[] = $item;
		}

		return array_values(array_unique($uris));
	}

	/** @param array<mixed> $payload */
	private function validateAuthMethod(array $payload): bool
	{
		if (!array_key_exists('token_endpoint_auth_method', $payload)) {
			return true;
		}

		return $payload['token_endpoint_auth_method'] === 'none';
	}

	/**
	 * @param array<mixed> $payload
	 * @return list<string>|null
	 */
	private function extractGrantTypes(array $payload): array|null
	{
		if (!array_key_exists('grant_types', $payload)) {
			return self::ALLOWED_GRANT_TYPES;
		}

		$raw = $payload['grant_types'];
		if (!is_array($raw) || $raw === [] || !array_is_list($raw)) {
			return null;
		}

		$types = [];
		foreach ($raw as $item) {
			if (!is_string($item) || !in_array($item, self::ALLOWED_GRANT_TYPES, true)) {
				return null;
			}

			$types[] = $item;
		}

		$unique = array_values(array_unique($types));
		if (!in_array('authorization_code', $unique, true)) {
			return null;
		}

		return $unique;
	}

	/**
	 * @param array<mixed> $payload
	 * @return list<string>|null
	 */
	private function extractResponseTypes(array $payload): array|null
	{
		if (!array_key_exists('response_types', $payload)) {
			return ['code'];
		}

		$raw = $payload['response_types'];
		if (!is_array($raw) || $raw !== ['code']) {
			return null;
		}

		return ['code'];
	}

	/** @param array<mixed> $payload */
	private function validateScope(array $payload): bool
	{
		if (!array_key_exists('scope', $payload)) {
			return true;
		}

		if (!is_string($payload['scope'])) {
			return false;
		}

		$trimmed = trim($payload['scope']);
		if ($trimmed === '') {
			return false;
		}

		$scopes = preg_split('/\s+/', $trimmed);
		if ($scopes === false || $scopes === []) {
			return false;
		}

		foreach ($scopes as $scope) {
			if ($scope !== OAuthScope::MCP_READ->value) {
				return false;
			}
		}

		return true;
	}

}
