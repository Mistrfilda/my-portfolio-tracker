<?php

declare(strict_types = 1);

namespace App\Api\Controller;

use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PingController
{

	public function ping(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$data = Json::encode(['status' => 'ok']);

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
