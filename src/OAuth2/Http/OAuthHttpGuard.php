<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

class OAuthHttpGuard
{

	public function __construct(private OAuthRateLimiter $limiter)
	{
	}

	public function check(ServerRequestInterface $request, string $bucket, int $limit = 60): void
	{
		$ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
		if (!is_string($ip) || !$this->limiter->allow($bucket . ':' . $ip, $limit)) {
			throw new OAuthServerException('Too many requests. Try again later.', 0, 'temporarily_unavailable', 429);
		}

		if (
			$request->getMethod() === 'POST'
			&& strtolower(
				explode(';', $request->getHeaderLine('Content-Type'))[0],
			) !== 'application/x-www-form-urlencoded'
		) {
			throw OAuthServerException::invalidRequest('Content-Type', 'Use application/x-www-form-urlencoded.');
		}
	}

}
