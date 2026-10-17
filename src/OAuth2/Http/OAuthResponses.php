<?php

declare(strict_types = 1);

namespace App\OAuth2\Http;

use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;

class OAuthResponses
{

	public function error(
		ResponseInterface $response,
		string $error,
		string $description,
		int $status,
	): ResponseInterface
	{
		$response->getBody()->write(Json::encode(['error' => $error, 'error_description' => $description]));
		return $this->noStore($response->withStatus($status)->withHeader('Content-Type', 'application/json'));
	}

	public function noStore(ResponseInterface $response): ResponseInterface
	{
		return $response->withHeader('Cache-Control', 'no-store')->withHeader('Pragma', 'no-cache');
	}

}
