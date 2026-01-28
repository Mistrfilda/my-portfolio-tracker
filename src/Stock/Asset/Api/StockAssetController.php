<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Api;

use App\Stock\Asset\StockAssetRepository;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StockAssetController
{

	public function __construct(
		private StockAssetRepository $stockAssetRepository,
		private StockAssetSerializer $stockAssetSerializer,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$stockAssets = $this->stockAssetRepository->findAll();

		$data = Json::encode($this->stockAssetSerializer->serializeList($stockAssets));

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
