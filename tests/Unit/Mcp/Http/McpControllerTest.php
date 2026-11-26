<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Http;

use App\Admin\CurrentAppAdminGetter;
use App\Api\ApiAuthenticationMiddleware;
use App\Mcp\Http\McpController;
use App\Mcp\McpServerFactory;
use App\Mcp\Prompt\InvestmentPrompts;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Tool\AiAnalysesTool;
use App\Mcp\Tool\InvestmentPlansTool;
use App\Mcp\Tool\PortfolioOverviewTool;
use App\Mcp\Tool\SearchStocksTool;
use App\Mcp\Tool\SimulateStockPurchaseTool;
use App\Mcp\Tool\StockDividendsTool;
use App\Mcp\Tool\StockPriceHistoryTool;
use App\Mcp\Tool\StockValuationDetailTool;
use App\Mcp\Tool\WatchlistTool;
use App\OAuth2\Authorization\OAuthUserAuthenticator;
use App\OAuth2\Client\OAuthClient;
use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Http\OAuthPublicOriginResolver;
use App\OAuth2\Http\OAuthResponses;
use App\OAuth2\OAuthScope;
use App\OAuth2\OAuthServerFactory;
use App\OAuth2\Token\OAuthTokenRecord;
use App\OAuth2\Token\OAuthTokenStore;
use App\OAuth2\Token\OAuthTokenType;
use League\OAuth2\Server\ResourceServer;
use Mcp\Server\Server;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class McpControllerTest extends TestCase
{

	public function testInitializeHandshakeIsStatelessAndAllowsLanHost(): void
	{
		$controller = new McpController(
			$this->createDefaultServerFactory(),
			new NullLogger(),
		);

		$request = $this->createJsonRpcRequest([
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'initialize',
			'params' => [
				'protocolVersion' => '2025-03-26',
				'capabilities' => [],
				'clientInfo' => [
					'name' => 'claude-code',
					'version' => '1.0.0',
				],
			],
		]);

		$response = $controller->handle($request, new Response());

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		self::assertSame('', $response->getHeaderLine('Mcp-Session-Id'));

		$body = Json::decode((string) $response->getBody(), forceArrays: true);
		self::assertIsArray($body);
		self::assertSame('2.0', $body['jsonrpc']);
		self::assertSame(1, $body['id']);
		self::assertIsArray($body['result']);
		self::assertIsArray($body['result']['serverInfo']);
		self::assertSame('my-portfolio-tracker', $body['result']['serverInfo']['name']);
	}

	public function testToolsListAndCallAndInvalidInputErrorHandling(): void
	{
		$serverFactory = $this->createServerFactoryWithTestTool();
		$controller = new McpController($serverFactory, new NullLogger());

		$listResponse = $controller->handle(
			$this->createJsonRpcRequest([
				'jsonrpc' => '2.0',
				'id' => 2,
				'method' => 'tools/list',
				'params' => [],
			]),
			new Response(),
		);

		self::assertSame(200, $listResponse->getStatusCode());
		$listBody = Json::decode((string) $listResponse->getBody(), forceArrays: true);
		self::assertIsArray($listBody);
		self::assertIsArray($listBody['result']);
		self::assertIsArray($listBody['result']['tools']);
		self::assertCount(1, $listBody['result']['tools']);
		self::assertIsArray($listBody['result']['tools'][0]);
		self::assertSame('echo_stock', $listBody['result']['tools'][0]['name']);

		$callResponse = $controller->handle(
			$this->createJsonRpcRequest([
				'jsonrpc' => '2.0',
				'id' => 3,
				'method' => 'tools/call',
				'params' => [
					'name' => 'echo_stock',
					'arguments' => ['ticker' => 'AAPL'],
				],
			]),
			new Response(),
		);

		self::assertSame(200, $callResponse->getStatusCode());
		$callBody = Json::decode((string) $callResponse->getBody(), forceArrays: true);
		self::assertIsArray($callBody);
		self::assertIsArray($callBody['result']);
		self::assertFalse($callBody['result']['isError'] ?? false);
		self::assertSame(['ticker' => 'AAPL'], $callBody['result']['structuredContent']);

		$errorResponse = $controller->handle(
			$this->createJsonRpcRequest([
				'jsonrpc' => '2.0',
				'id' => 4,
				'method' => 'tools/call',
				'params' => [
					'name' => 'echo_stock',
					'arguments' => ['ticker' => 'UNKNOWN'],
				],
			]),
			new Response(),
		);

		self::assertSame(200, $errorResponse->getStatusCode());
		$errorBody = Json::decode((string) $errorResponse->getBody(), forceArrays: true);
		self::assertIsArray($errorBody);
		self::assertIsArray($errorBody['result']);
		self::assertTrue($errorBody['result']['isError']);
	}

	public function testRejectsGetAndDeleteInStatelessMode(): void
	{
		$controller = new McpController(
			$this->createDefaultServerFactory(),
			new NullLogger(),
		);

		$getRequest = (new ServerRequestFactory())
			->createServerRequest('GET', 'http://portfolio.lan/api/mcp')
			->withHeader('Host', 'portfolio.lan');
		$getResponse = $controller->handle($getRequest, new Response());
		self::assertSame(405, $getResponse->getStatusCode());

		$deleteRequest = (new ServerRequestFactory())
			->createServerRequest('DELETE', 'http://portfolio.lan/api/mcp')
			->withHeader('Host', 'portfolio.lan');
		$deleteResponse = $controller->handle($deleteRequest, new Response());
		self::assertSame(405, $deleteResponse->getStatusCode());
	}

	public function testRejectsDisallowedCrossOriginBrowserRequest(): void
	{
		$controller = new McpController(
			$this->createDefaultServerFactory(),
			new NullLogger(),
			corsAllowedOrigins: [],
		);

		$request = $this->createJsonRpcRequest([
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'ping',
		])->withHeader('Origin', 'https://evil.example');

		$response = $controller->handle($request, new Response());

		self::assertSame(403, $response->getStatusCode());
	}

	public function testMiddlewareRejectsUnauthenticatedTokenWithoutMcpScopeAndClientOnlyToken(): void
	{
		$controller = new McpController($this->createDefaultServerFactory(), new NullLogger());
		$handler = new readonly class ($controller) implements RequestHandlerInterface {

			public function __construct(private McpController $controller)
			{
			}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return $this->controller->handle($request, new Response());
			}

		};

		// 1. Unauthenticated request -> 401 with resource_metadata
		$unauthMiddleware = $this->createMcpMiddleware(tokenRecord: null, client: null);
		$unauthResponse = $unauthMiddleware->process(
			$this->createJsonRpcRequest(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']),
			$handler,
		);
		self::assertSame(401, $unauthResponse->getStatusCode());
		self::assertStringContainsString(
			'resource_metadata="http://portfolio.lan/.well-known/oauth-protected-resource/api/mcp"',
			$unauthResponse->getHeaderLine('WWW-Authenticate'),
		);

		// 2. Token without mcp:read scope -> 403 insufficient_scope
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$wrongScopeRecord = new OAuthTokenRecord(
			'tok-1',
			OAuthTokenType::ACCESS,
			'client-1',
			'user-1',
			[OAuthScope::API_READ->value],
			$now,
			false,
			null,
			null,
			null,
		);
		$wrongScopeClient = new OAuthClient(
			'client-1',
			'App',
			false,
			null,
			['http://localhost:54321/callback'],
			[OAuthScope::API_READ->value],
			$now,
		);
		$wrongScopeMiddleware = $this->createMcpMiddleware($wrongScopeRecord, $wrongScopeClient);
		$wrongScopeResponse = $wrongScopeMiddleware->process(
			$this->createJsonRpcRequest(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
				->withHeader('Authorization', 'Bearer valid-token'),
			$handler,
		);
		self::assertSame(403, $wrongScopeResponse->getStatusCode());
		$wrongScopeBody = Json::decode((string) $wrongScopeResponse->getBody(), forceArrays: true);
		self::assertIsArray($wrongScopeBody);
		self::assertSame('insufficient_scope', $wrongScopeBody['error']);

		// 3. Client-only token with mcp:read but no userId -> 403 access_denied
		$clientOnlyRecord = new OAuthTokenRecord(
			'tok-2',
			OAuthTokenType::ACCESS,
			'client-2',
			null,
			[OAuthScope::MCP_READ->value],
			$now,
			false,
			null,
			null,
			null,
		);
		$mcpClient = new OAuthClient(
			'client-2',
			'Bot',
			true,
			'hash',
			[],
			[OAuthScope::MCP_READ->value],
			$now,
		);
		$clientOnlyMiddleware = $this->createMcpMiddleware($clientOnlyRecord, $mcpClient);
		$clientOnlyResponse = $clientOnlyMiddleware->process(
			$this->createJsonRpcRequest(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
				->withHeader('Authorization', 'Bearer valid-token'),
			$handler,
		);
		self::assertSame(403, $clientOnlyResponse->getStatusCode());
		$clientOnlyBody = Json::decode((string) $clientOnlyResponse->getBody(), forceArrays: true);
		self::assertIsArray($clientOnlyBody);
		self::assertSame('access_denied', $clientOnlyBody['error']);
	}

	private function createMcpMiddleware(
		OAuthTokenRecord|null $tokenRecord,
		OAuthClient|null $client,
	): ApiAuthenticationMiddleware
	{
		$resourceServer = self::createStub(ResourceServer::class);
		$resourceServer->method('validateAuthenticatedRequest')->willReturnCallback(
			static fn (ServerRequestInterface $request): ServerRequestInterface => $request->withAttribute(
				'oauth_access_token_id',
				$tokenRecord?->getId() ?? '',
			),
		);

		$servers = self::createStub(OAuthServerFactory::class);
		$servers->method('createResourceServer')->willReturn($resourceServer);

		$tokens = self::createStub(OAuthTokenStore::class);
		$tokens->method('find')->willReturn($tokenRecord);

		$clients = self::createStub(OAuthClientRepository::class);
		$clients->method('getClientEntity')->willReturn($client);

		return new ApiAuthenticationMiddleware(
			OAuthScope::MCP_READ,
			false,
			self::createStub(MiddlewareInterface::class),
			$servers,
			$tokens,
			$clients,
			self::createStub(OAuthUserAuthenticator::class),
			self::createStub(CurrentAppAdminGetter::class),
			new OAuthResponses(),
			requireUser: true,
			resourceMetadataPath: '/.well-known/oauth-protected-resource/api/mcp',
			originResolver: new OAuthPublicOriginResolver(),
		);
	}

	public function testDefaultServerExposesAllNineToolsAndThreePrompts(): void
	{
		$controller = new McpController($this->createDefaultServerFactory(), new NullLogger());

		$toolsResponse = $controller->handle(
			$this->createJsonRpcRequest([
				'jsonrpc' => '2.0',
				'id' => 10,
				'method' => 'tools/list',
				'params' => [],
			]),
			new Response(),
		);
		self::assertSame(200, $toolsResponse->getStatusCode());
		$toolsBody = Json::decode((string) $toolsResponse->getBody(), forceArrays: true);
		self::assertIsArray($toolsBody);
		self::assertIsArray($toolsBody['result']);
		self::assertIsArray($toolsBody['result']['tools']);
		self::assertCount(9, $toolsBody['result']['tools']);

		$promptsResponse = $controller->handle(
			$this->createJsonRpcRequest([
				'jsonrpc' => '2.0',
				'id' => 11,
				'method' => 'prompts/list',
				'params' => [],
			]),
			new Response(),
		);
		self::assertSame(200, $promptsResponse->getStatusCode());
		$promptsBody = Json::decode((string) $promptsResponse->getBody(), forceArrays: true);
		self::assertIsArray($promptsBody);
		self::assertIsArray($promptsBody['result']);
		self::assertIsArray($promptsBody['result']['prompts']);
		self::assertCount(3, $promptsBody['result']['prompts']);

		$promptGetResponse = $controller->handle(
			$this->createJsonRpcRequest([
				'jsonrpc' => '2.0',
				'id' => 12,
				'method' => 'prompts/get',
				'params' => [
					'name' => 'evaluate_buy_candidate',
					'arguments' => ['ticker' => 'AAPL'],
				],
			]),
			new Response(),
		);
		self::assertSame(200, $promptGetResponse->getStatusCode());
		$promptGetBody = Json::decode((string) $promptGetResponse->getBody(), forceArrays: true);
		self::assertIsArray($promptGetBody);
		self::assertIsArray($promptGetBody['result']);
		self::assertIsArray($promptGetBody['result']['messages']);
		self::assertCount(1, $promptGetBody['result']['messages']);
	}

	private function createDefaultServerFactory(): McpServerFactory
	{
		return new McpServerFactory(
			new NullLogger(),
			self::createStub(PortfolioOverviewTool::class),
			self::createStub(WatchlistTool::class),
			self::createStub(SearchStocksTool::class),
			self::createStub(StockPriceHistoryTool::class),
			self::createStub(StockDividendsTool::class),
			self::createStub(StockValuationDetailTool::class),
			self::createStub(AiAnalysesTool::class),
			self::createStub(InvestmentPlansTool::class),
			self::createStub(SimulateStockPurchaseTool::class),
			new InvestmentPrompts(),
		);
	}

	private function createServerFactoryWithTestTool(): McpServerFactory
	{
		$logger = new NullLogger();

		return new class (
			$logger,
			self::createStub(PortfolioOverviewTool::class),
			self::createStub(WatchlistTool::class),
			self::createStub(SearchStocksTool::class),
			self::createStub(StockPriceHistoryTool::class),
			self::createStub(StockDividendsTool::class),
			self::createStub(StockValuationDetailTool::class),
			self::createStub(AiAnalysesTool::class),
			self::createStub(InvestmentPlansTool::class),
			self::createStub(SimulateStockPurchaseTool::class),
			new InvestmentPrompts(),
		) extends McpServerFactory {

			public function create(): Server
			{
				return $this->createBuilder()
					->addTool(
						static function (string $ticker): array {
							if ($ticker === 'UNKNOWN') {
								throw new McpInvalidInputException('Stock ticker UNKNOWN was not found.');
							}

							return ['ticker' => $ticker];
						},
						name: 'echo_stock',
						description: 'Echoes ticker for test purposes.',
						annotations: $this->createReadOnlyToolAnnotations('Echo Stock'),
					)
					->build();
			}

		};
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function createJsonRpcRequest(array $payload): ServerRequestInterface
	{
		$request = (new ServerRequestFactory())
			->createServerRequest('POST', 'http://portfolio.lan/api/mcp')
			->withHeader('Host', 'portfolio.lan')
			->withHeader('Content-Type', 'application/json')
			->withHeader('Accept', 'application/json, text/event-stream');
		$request->getBody()->write(Json::encode($payload));
		$request->getBody()->rewind();

		return $request;
	}

}
