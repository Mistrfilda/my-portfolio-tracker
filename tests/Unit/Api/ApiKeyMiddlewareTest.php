<?php

declare(strict_types = 1);

namespace App\Test\Unit\Api;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Api\ApiKeyMiddleware;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\UuidInterface;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Psr7\Factory\ServerRequestFactory;

class ApiKeyMiddlewareTest extends TestCase
{

	#[DataProvider('provideInvalidApiKeys')]
	public function testRejectsMissingOrInvalidApiKey(string|null $apiKey): void
	{
		$currentAppAdminGetter = $this->createMock(CurrentAppAdminGetter::class);
		$currentAppAdminGetter->expects(self::never())->method('setApiAppAdmin');
		$appAdminRepository = $this->createMock(AppAdminRepository::class);
		$appAdminRepository->expects(self::never())->method('findById');
		$handler = $this->createMock(RequestHandlerInterface::class);
		$handler->expects(self::never())->method('handle');
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/ping');
		if ($apiKey !== null) {
			$request = $request->withHeader('X-Api-Key', $apiKey);
		}

		$middleware = new ApiKeyMiddleware(
			[['apiKey' => 'valid-key', 'appAdminId' => '00000000-0000-0000-0000-000000000001']],
			$currentAppAdminGetter,
			$appAdminRepository,
		);

		$this->expectException(HttpUnauthorizedException::class);
		$this->expectExceptionMessage('Invalid API key provided');

		$middleware->process($request, $handler);
	}

	public function testLoadsConfiguredAdminAndContinuesRequest(): void
	{
		$appAdminId = '00000000-0000-0000-0000-000000000001';
		$appAdmin = $this->createStub(AppAdmin::class);
		$appAdminRepository = $this->createMock(AppAdminRepository::class);
		$appAdminRepository->expects(self::once())
			->method('findById')
			->with(self::callback(static fn (UuidInterface $id): bool => $id->toString() === $appAdminId))
			->willReturn($appAdmin);
		$currentAppAdminGetter = $this->createMock(CurrentAppAdminGetter::class);
		$currentAppAdminGetter->expects(self::once())->method('setApiAppAdmin')->with($appAdmin);
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/ping')
			->withHeader('X-Api-Key', 'valid-key');
		$response = new Response(204);
		$handler = $this->createMock(RequestHandlerInterface::class);
		$handler->expects(self::once())->method('handle')->with($request)->willReturn($response);
		$middleware = new ApiKeyMiddleware(
			[
				['apiKey' => 'other-key', 'appAdminId' => '00000000-0000-0000-0000-000000000002'],
				['apiKey' => 'valid-key', 'appAdminId' => $appAdminId],
			],
			$currentAppAdminGetter,
			$appAdminRepository,
		);

		self::assertSame($response, $middleware->process($request, $handler));
	}

	/**
	 * @return array<string, array{string|null}>
	 */
	public static function provideInvalidApiKeys(): array
	{
		return [
			'missing header' => [null],
			'empty header' => [''],
			'unknown key' => ['invalid-key'],
		];
	}

}
