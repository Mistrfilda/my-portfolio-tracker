<?php

declare(strict_types = 1);

namespace App\Test\Unit\Dashboard\Api;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\MissingCurrencyPairException;
use App\Dashboard\Api\DashboardSummaryController;
use App\Dashboard\Api\DashboardSummaryProvider;
use App\OAuth2\Http\OAuthResponses;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class DashboardSummaryControllerTest extends TestCase
{

	public function testMissingExchangeRatesReturnUnavailableWithoutPartialTotals(): void
	{
		foreach ([new NoResultException(), new MissingCurrencyPairException()] as $exception) {
			$provider = $this->createStub(DashboardSummaryProvider::class);
			$provider->method('getSummary')->willThrowException($exception);
			$controller = new DashboardSummaryController(
				$provider,
				$this->createStub(CurrentAppAdminGetter::class),
				new OAuthResponses(),
			);
			$response = $controller->summary(
				new ServerRequestFactory()->createServerRequest('GET', '/api/v1/dashboard/summary'),
				new Response(),
			);
			self::assertSame(503, $response->getStatusCode());
			self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
			self::assertSame([
				'error' => 'data_unavailable',
				'error_description' => 'Required exchange rate data is unavailable.',
			], Json::decode((string) $response->getBody(), true));
		}
	}

}
