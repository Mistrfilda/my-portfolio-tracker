<?php

declare(strict_types = 1);

namespace App\Api\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class PingController
{

	public function ping(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$data = json_encode(['status' => 'ok']);
		if ($data === false) {
			throw new RuntimeException('Failed to encode JSON');
		}

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
