<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Api;

use App\Admin\CurrentAppAdminGetter;
use App\OAuth2\Http\OAuthResponses;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Utils\TypeValidator;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StockDividendController
{

	public function __construct(
		private StockAssetDividendRepository $dividends,
		private StockAssetDividendRecordRepository $records,
		private StockDividendSerializer $serializer,
		private CurrentAppAdminGetter $currentUser,
		private OAuthResponses $responses,
	)
	{
	}

	public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$query = $request->getQueryParams();
		try {
			$from = $this->date($query['exDateFrom'] ?? null);
			$to = $this->date($query['exDateTo'] ?? null);
		} catch (InvalidArgumentException) {
			return $this->responses->error(
				$response,
				'invalid_request',
				'Expected a valid date in YYYY-MM-DD format.',
				400,
			);
		}

		if ($from !== null && $to !== null && $from > $to) {
			return $this->responses->error($response, 'invalid_request', 'exDateFrom must not exceed exDateTo.', 400);
		}

		$items = $this->dividends->findForApi(
			TypeValidator::validateInt($query['limit'] ?? 8),
			$from,
			$to,
			TypeValidator::validateString($query['sort'] ?? 'exDateDesc') === 'exDateAsc',
		);
		$response->getBody()->write(Json::encode(['items' => array_map($this->serializer->serialize(...), $items)]));
		return $response->withHeader('Content-Type', 'application/json');
	}

	public function portfolio(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$items = $this->records->findLastDividendRecords(
			TypeValidator::validateInt($request->getQueryParams()['limit'] ?? 8),
			$this->currentUser->getAppAdmin(),
		);
		$response->getBody()->write(Json::encode([
			'items' => array_map($this->serializer->serializeRecord(...), $items),
		]));
		return $response->withHeader('Content-Type', 'application/json');
	}

	private function date(mixed $value): ImmutableDateTime|null
	{
		$value = TypeValidator::validateNullableString($value);
		if ($value === null) {
			return null;
		}

		if (
			preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) !== 1
			|| !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
		) {
			throw new InvalidArgumentException('Invalid calendar date.');
		}

		return new ImmutableDateTime($value);
	}

}
