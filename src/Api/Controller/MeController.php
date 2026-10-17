<?php

declare(strict_types = 1);

namespace App\Api\Controller;

use App\Api\ApiPrincipal;
use App\OAuth2\Http\OAuthResponses;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MeController
{

	public function __construct(private OAuthResponses $responses)
	{
	}

	public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$principal = $request->getAttribute('api_principal');
		if (!$principal instanceof ApiPrincipal || $principal->user === null) {
			return $this->responses->error(
				$response,
				'access_denied',
				'This operation requires user authorization.',
				403,
			);
		}

		$response->getBody()->write(Json::encode([
			'id' => $principal->user->getId()->toString(),
			'name' => $principal->user->getName(),
			'username' => $principal->user->getUsername(),
		]));
		return $this->responses->noStore($response->withHeader('Content-Type', 'application/json'));
	}

}
