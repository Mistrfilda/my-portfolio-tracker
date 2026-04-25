<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Api;

use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAssetRepository;
use App\Utils\TypeValidator;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

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
		$stockAssets = $this->stockAssetRepository->findAllActive();

		$data = Json::encode($this->stockAssetSerializer->serializeList($stockAssets));

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

	public function detail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$stockAssetId = TypeValidator::validateString($request->getAttribute('id'));
		if (Uuid::isValid($stockAssetId) === false) {
			return $this->createNotFoundResponse($response);
		}

		try {
			$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($stockAssetId));
		} catch (NoEntityFoundException) {
			return $this->createNotFoundResponse($response);
		}

		$data = Json::encode($this->stockAssetSerializer->serializeDetail($stockAsset));

		$response->getBody()->write($data);
		return $response->withHeader('Content-Type', 'application/json');
	}

	private function createNotFoundResponse(ResponseInterface $response): ResponseInterface
	{
		$response->getBody()->write(Json::encode([
			'error' => 'Stock asset not found.',
		]));

		return $response
			->withHeader('Content-Type', 'application/json')
			->withStatus(404);
	}

}
