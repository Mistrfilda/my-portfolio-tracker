<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Home\Device\HomeDevice;
use App\Home\Device\HomeDeviceType;
use App\Home\Device\Record\HomeDeviceRecord;
use App\Home\Device\Record\HomeDeviceRecordRepository;
use App\Home\Device\Record\HomeDeviceRecordUnit;
use App\Home\Home;
use App\Stock\AiAnalysis\StockAiAnalysisResultTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\UI\Filter\CurrencyFilter;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

class ApiDataIsolationTest extends ApiTestCase
{

	private EntityManagerInterface $em;

	/** @var list<AppAdmin> */
	private array $owners = [];

	/** @var list<string> */
	private array $tokens = [];

	/** @var list<StockPosition> */
	private array $positions = [];

	/** @var list<StockAssetDividendRecord> */
	private array $dividendRecords = [];

	/** @var list<HomeDevice> */
	private array $devices = [];

	private StockAsset $stock;

	private StockAssetDividend $dividend;

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$now = $this->createDatetime();
		$suffix = bin2hex(random_bytes(5));
		$this->owners[] = $this->legacyApiAdmin;
		foreach (['other', 'empty'] as $name) {
			$username = 'api-isolation-' . $name . '-' . $suffix;
			$owner = new AppAdmin($username, $username, $username . '@example.test', 'unused', $now, false, false);
			$this->em->persist($owner);
			$this->owners[] = $owner;
		}

		$this->stock = new StockAsset(
			'API isolation ' . $suffix,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'API-' . $suffix,
			StockAssetExchange::NYSE,
			CurrencyEnum::CZK,
			$now,
			null,
			null,
			15.0,
			null,
			true,
			false,
			false,
			null,
		);
		$this->em->persist($this->stock);
		$price = new StockAssetPriceRecord(
			$now,
			CurrencyEnum::CZK,
			150,
			$this->stock,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$now,
		);
		$this->stock->setCurrentPrice($price, $now);
		$this->em->persist($price);
		$this->dividend = new StockAssetDividend(
			$this->stock,
			$now->deductDaysFromDatetime(2),
			$now,
			null,
			CurrencyEnum::CZK,
			2.0,
			$now,
		);
		$this->em->persist($this->dividend);
		foreach ([10, 20] as $index => $pieces) {
			$owner = $this->owners[$index];
			$position = new StockPosition(
				$owner,
				$this->stock,
				$pieces,
				100,
				$now->deductDaysFromDatetime(10),
				new AssetPriceEmbeddable($pieces * 100, CurrencyEnum::CZK),
				false,
				$now,
			);
			$this->positions[] = $position;
			$this->em->persist($position);
			$record = new StockAssetDividendRecord(
				$this->dividend,
				$pieces,
				$pieces * 2,
				CurrencyEnum::CZK,
				null,
				null,
				$now,
				$owner,
			);
			$this->dividendRecords[] = $record;
			$this->em->persist($record);
			$this->createAiResult($owner, ($index + 1) * 111, $now);

			$home = new Home($owner, 'API home ' . $index, $now);
			$device = new HomeDevice(
				$home,
				'api-device-' . $suffix . '-' . $index,
				'Sensor',
				HomeDeviceType::TEMPERATURE,
				$now,
			);
			$this->em->persist($home);
			$this->em->persist($device);
			$this->em->persist(
				new HomeDeviceRecord($device, $owner, null, 10.5 + $index, null, HomeDeviceRecordUnit::CELSIUS, $now),
			);
			$this->devices[] = $device;
		}

		$this->em->flush();
		foreach ($this->owners as $owner) {
			$this->tokens[] = $this->getUserOAuthAccessToken($owner, ['api:read', 'home:read', 'home:write']);
		}

		$this->em->clear();
		$rates = $this->createStub(CurrencyConversionRepository::class);
		$rates->method('getCurrentCurrencyPairConversion')->willReturnCallback(
			static fn (CurrencyEnum $from, CurrencyEnum $to): CurrencyConversion => new CurrencyConversion(
				$from,
				$to,
				1,
				CurrencySourceEnum::CNB,
				$now,
				$now,
			),
		);
		foreach ($this->container->findByType(CurrencyConversionRepository::class) as $service) {
			$this->container->removeService($service);
			$this->container->addService($service, $rates);
		}
	}

	public function testStockDetailUsesOnlyTokenOwnersPositionsDividendsAndAi(): void
	{
		foreach ([0, 1, 2, 1, 0] as $index) {
			$data = $this->data($this->api('GET', '/stocks/' . $this->stock->getId(), $this->tokens[$index]));
			self::assertCount($index === 2 ? 0 : 1, $data['openPositions']);
			self::assertCount(1, $data['dividends']);
			$dividend = $data['dividends'][0];
			self::assertSame($this->dividend->getId()->toString(), $dividend['id']);
			if ($index === 2) {
				self::assertSame([], $dividend['records']);
				self::assertNull($dividend['paidAmount']);
				self::assertNull($data['aiAnalysisPrice']);
			} else {
				self::assertSame($this->positions[$index]->getId()->toString(), $data['openPositions'][0]['id']);
				self::assertSame(($index + 1) * 10, $data['openPositions'][0]['orderPiecesCount']);
				self::assertSame(
					[$this->dividendRecords[$index]->getId()->toString()],
					array_column($dividend['records'], 'id'),
				);
				self::assertEquals(['amount' => ($index + 1) * 20, 'currency' => 'CZK'], $dividend['paidAmount']);
				self::assertEquals(['price' => ($index + 1) * 111, 'currency' => 'CZK'], $data['aiAnalysisPrice']);
			}

			self::assertNull($this->getService(CurrentAppAdminGetter::class)->getAppAdminOrNull());
		}
	}

	public function testStockCatalogKeepsMarketDataSharedAndAiPricesPersonal(): void
	{
		$clientToken = $this->getOAuthAccessToken();
		foreach ([$this->tokens[0], $clientToken, $this->tokens[1], $this->tokens[2], $clientToken] as $index => $token) {
			$data = $this->data($this->api('GET', '/stocks', $token));
			$stocks = array_column($data, null, 'id');
			$stock = $stocks[$this->stock->getId()->toString()];
			self::assertSame(150.0, $stock['price']);
			self::assertArrayNotHasKey('openPositions', $stock);
			$expectedPrice = match ($index) {
				0 => ['price' => 111.0, 'currency' => 'CZK'],
				2 => ['price' => 222.0, 'currency' => 'CZK'],
				default => null,
			};
			self::assertSame($expectedPrice, $stock['aiAnalysisPrice']);
		}
	}

	public function testDashboardAndMeFollowTokenOwnerIncludingEmptyAccount(): void
	{
		foreach ([0, 1, 2, 1, 0] as $index) {
			$me = $this->data($this->api('GET', '/me', $this->tokens[$index]));
			self::assertSame($this->owners[$index]->getId()->toString(), $me['id']);
			$data = $this->data($this->api(
				'GET',
				'/dashboard/values?userId=' . $this->owners[1]->getId(),
				$this->tokens[$index],
			));
			$groups = array_column($data, null, 'name');
			$factor = $index === 2 ? 0 : $index + 1;
			self::assertSame(
				CurrencyFilter::format($factor * 1000, CurrencyEnum::CZK),
				$groups['total_values']['positions'][0]['value'],
			);
			self::assertSame(
				CurrencyFilter::format($factor * 1500, CurrencyEnum::CZK),
				$groups['total_values']['positions'][1]['value'],
			);
			self::assertCount($index === 2 ? 0 : 1, $groups['dividends']['tables'][0]['data']);
			if ($index !== 2) {
				self::assertSame(
					CurrencyFilter::format($factor * 17, CurrencyEnum::CZK, 0),
					$groups['dividends']['positions'][0]['value'],
				);
			}
		}
	}

	public function testClientOnlyTokenCannotAccessPersonalEndpointsEvenWithAllScopes(): void
	{
		$token = $this->getOAuthAccessToken(['api:read', 'home:read', 'home:write']);
		foreach (['/me', '/dashboard/values', '/stocks/' . $this->stock->getId(), '/home/device/records'] as $path) {
			$response = $this->api('GET', $path, $token);
			self::assertSame('access_denied', $this->data($response, 403)['error']);
		}

		$response = $this->api(
			'POST',
			'/home/device/record',
			$token,
			['internalId' => $this->devices[0]->getInternalId(), 'value' => 999],
		);
		self::assertSame('access_denied', $this->data($response, 403)['error']);
		self::assertSame(200, $this->api('GET', '/ping', $token)->getStatusCode());
	}

	public function testHomeListsFollowUserAndLegacyKeyOwner(): void
	{
		foreach ([0, 1, 2, 0] as $index) {
			$data = $this->data($this->api('GET', '/home/device/records', $this->tokens[$index]));
			$expected = $index === 2 ? [] : [$this->devices[$index]->getId()->toString()];
			self::assertSame($expected, array_column($data, 'id'));
			if ($index !== 2) {
				self::assertSame(10.5 + $index, $data[0]['latestRecord']['value']);
			}
		}

		$request = new ServerRequestFactory()->createServerRequest('GET', '/api/v1/home/device/records')
			->withHeader('X-Api-Key', 'test-api-key');
		$data = $this->data($this->app->handle($request));
		self::assertSame([$this->devices[0]->getId()->toString()], array_column($data, 'id'));
	}

	public function testHomeWritesRejectForeignInternalIdForUserAndLegacyKey(): void
	{
		$body = ['internalId' => $this->devices[1]->getInternalId(), 'value' => 999];
		$foreign = $this->data($this->api('POST', '/home/device/record', $this->tokens[0], $body), 404);
		$missing = $this->data(
			$this->api('POST', '/home/device/record', $this->tokens[0], ['internalId' => 'missing', 'value' => 999]),
			404,
		);
		self::assertSame($missing, $foreign);
		$request = new ServerRequestFactory()->createServerRequest('POST', '/api/v1/home/device/record')
			->withHeader('X-Api-Key', 'test-api-key')
			->withHeader('Content-Type', 'application/json')
			->withBody(new StreamFactory()->createStream(Json::encode($body)));
		self::assertSame($foreign, $this->data($this->app->handle($request), 404));
		$records = $this->getService(HomeDeviceRecordRepository::class)->findAllForAppAdmin($this->owners[1]);
		self::assertCount(1, $records);
		self::assertSame(11.5, $records[0]->getFloatValue());
	}

	public function testUserCanWriteOwnDeviceAndCannotUseReadScopeForWriting(): void
	{
		$body = ['internalId' => $this->devices[1]->getInternalId(), 'value' => 23.5];
		$data = $this->data($this->api('POST', '/home/device/record', $this->tokens[1], $body));
		self::assertSame($this->devices[1]->getInternalId(), $data['deviceInternalId']);
		$records = $this->getService(HomeDeviceRecordRepository::class)->findAllForAppAdmin($this->owners[1]);
		self::assertCount(2, $records);
		foreach ($records as $record) {
			self::assertSame($this->owners[1]->getId()->toString(), $record->getCreatedBy()?->getId()->toString());
		}

		$readToken = $this->getUserOAuthAccessToken($this->owners[1], ['home:read']);
		self::assertSame(
			'insufficient_scope',
			$this->data($this->api('POST', '/home/device/record', $readToken, $body), 403)['error'],
		);
	}

	/** @param array<string, mixed>|null $body */
	private function api(string $method, string $path, string $token, array|null $body = null): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest($method, '/api/v1' . $path)
			->withHeader('Authorization', 'Bearer ' . $token);
		if ($body !== null) {
			$request = $request->withHeader('Content-Type', 'application/json')
				->withBody(new StreamFactory()->createStream(Json::encode($body)));
		}

		return $this->app->handle($request);
	}

	/** @return array<mixed> */
	private function data(ResponseInterface $response, int $status = 200): array
	{
		self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$data = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertIsArray($data);
		return $data;
	}

	private function createAiResult(AppAdmin $owner, float $fairPrice, ImmutableDateTime $now): void
	{
		$run = new StockAiAnalysisRun($owner, 'Synthetic API isolation analysis', false, false, false, null, $now);
		$this->em->persist($run);
		$this->em->persist(new StockAiAnalysisStockResult(
			stockAiAnalysisRun: $run,
			stockAsset: $this->stock,
			type: StockAiAnalysisResultTypeEnum::SINGLE_STOCK,
			positiveNews: null,
			negativeNews: null,
			interestingNews: null,
			aiOpinion: null,
			actionSuggestion: null,
			reasoning: null,
			news: null,
			stockTicker: $this->stock->getTicker(),
			stockName: $this->stock->getName(),
			businessSummary: null,
			moatAnalysis: null,
			financialHealth: null,
			growthCatalysts: null,
			valuationAssessment: null,
			conclusion: null,
			risks: null,
			earningsCommentary: null,
			dividendAnalysis: null,
			performance7DaysComment: null,
			performance1DayComment: null,
			confidenceLevel: null,
			fairPrice: $fairPrice,
			fairPriceCurrency: CurrencyEnum::CZK,
			now: $now,
		));
	}

}
