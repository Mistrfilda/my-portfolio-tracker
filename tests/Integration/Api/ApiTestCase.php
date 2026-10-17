<?php

declare(strict_types = 1);

namespace App\Test\Integration\Api;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Api\ApiKeyMiddleware;
use App\Api\SlimAppFactory;
use App\OAuth2\Client\OAuthClientFacade;
use App\OAuth2\OAuthKeys;
use App\Test\Integration\IntegrationTestCase;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Bootstrap\Configurator;
use Nette\Utils\Json;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use function assert;

abstract class ApiTestCase extends IntegrationTestCase
{

	/** @var App<ContainerInterface|null> */
	protected App $app;

	protected AppAdmin $legacyApiAdmin;

	protected function configureContainer(Configurator $configurator): void
	{
		$configurator->addConfig([
			'session' => ['autoStart' => 'never'],
			'parameters' => ['api' => ['oauth' => ['keysDirectory' => __DIR__ . '/../../../temp/oauth-tests-' . getmypid()]]],
		]);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$name = 'sensor-test-' . bin2hex(random_bytes(10));
		$this->legacyApiAdmin = new AppAdmin(
			$name,
			$name,
			$name . '@example.test',
			'unused-test-password',
			$this->createDatetime(),
			false,
			false,
		);
		$entityManager = $this->getService(EntityManagerInterface::class);
		$entityManager->persist($this->legacyApiAdmin);
		$entityManager->flush();
		$this->container->removeService('apiKeyMiddleware');
		$this->container->addService('apiKeyMiddleware', new ApiKeyMiddleware(
			[['apiKey' => 'test-api-key', 'appAdminId' => $this->legacyApiAdmin->getId()->toString()]],
			$this->getService(CurrentAppAdminGetter::class),
			$this->getService(AppAdminRepository::class),
		));
		if (!is_file(__DIR__ . '/../../../temp/oauth-tests-' . getmypid() . '/private.key')) {
			$this->getService(OAuthKeys::class)->generate();
		}

		$slimAppFactory = $this->container->getByType(SlimAppFactory::class);
		assert($slimAppFactory instanceof SlimAppFactory);
		$this->app = $slimAppFactory->create();
	}

	protected function getOAuthAccessToken(): string
	{
		$client = $this->getService(OAuthClientFacade::class)->create('API test client', true, ['api:read'], []);
		$request = (new ServerRequestFactory())->createServerRequest(
			'POST',
			'https://localhost/api/oauth/token',
			['REMOTE_ADDR' => $client->client->getIdentifier()],
		)
			->withHeader('Content-Type', 'application/x-www-form-urlencoded')
			->withParsedBody(
				['grant_type' => 'client_credentials', 'client_id' => $client->client->getIdentifier(), 'client_secret' => $client->secret],
			);
		$response = $this->app->handle($request);
		self::assertSame(200, $response->getStatusCode());
		$data = TypeValidator::validateArray(Json::decode((string) $response->getBody(), true));
		return TypeValidator::validateString($data['access_token']);
	}

}
