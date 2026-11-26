<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use Psr\Http\Message\ServerRequestInterface;

class OAuthPublicOriginResolver
{

	public function __construct(private string|null $publicBaseUrl = null)
	{
	}

	public function resolve(ServerRequestInterface $request): string
	{
		if ($this->publicBaseUrl !== null && trim($this->publicBaseUrl) !== '') {
			return rtrim(trim($this->publicBaseUrl), '/');
		}

		$uri = $request->getUri();
		$scheme = $this->resolveScheme($request, $uri->getScheme());
		$authority = $this->resolveAuthority($request, $scheme, $uri->getAuthority());

		return $scheme . '://' . $authority;
	}

	private function resolveScheme(ServerRequestInterface $request, string $uriScheme): string
	{
		$forwardedProto = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Proto'));
		if ($forwardedProto !== null) {
			$normalized = strtolower($forwardedProto);
			if (in_array($normalized, ['http', 'https'], true)) {
				return $normalized;
			}
		}

		$normalizedUriScheme = strtolower($uriScheme);
		return in_array($normalizedUriScheme, ['http', 'https'], true) ? $normalizedUriScheme : 'http';
	}

	private function resolveAuthority(ServerRequestInterface $request, string $scheme, string $uriAuthority): string
	{
		$forwardedHost = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Host'));
		if ($forwardedHost !== null && preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $forwardedHost) === 1) {
			$hasPort = str_contains($forwardedHost, ':') && !str_ends_with($forwardedHost, ']');
			if (!$hasPort) {
				$forwardedPort = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Port'));
				if (
					$forwardedPort !== null
					&& preg_match('/^\d{1,5}$/', $forwardedPort) === 1
					&& !$this->isDefaultPort($scheme, (int) $forwardedPort)
				) {
					return $forwardedHost . ':' . $forwardedPort;
				}
			}

			return $forwardedHost;
		}

		return $uriAuthority !== '' ? $uriAuthority : 'localhost';
	}

	private function firstHeaderValue(string $header): string|null
	{
		if (trim($header) === '') {
			return null;
		}

		$parts = explode(',', $header);
		$first = trim($parts[0]);
		return $first === '' ? null : $first;
	}

	private function isDefaultPort(string $scheme, int $port): bool
	{
		return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
	}

}
