<?php

declare(strict_types = 1);

namespace App\Test\Integration\OAuth2;

use App\OAuth2\Client\OAuthClientRepository;
use App\OAuth2\Command\CreateOAuthClientCommand;
use App\OAuth2\Command\DisableOAuthClientCommand;
use App\OAuth2\Command\RotateOAuthClientSecretCommand;
use App\OAuth2\Command\UpdateOAuthClientScopesCommand;
use App\Test\Integration\IntegrationTestCase;
use App\Utils\TypeValidator;
use Nette\Utils\Json;
use Symfony\Component\Console\Tester\CommandTester;

class OAuthCommandsTest extends IntegrationTestCase
{

	public function testCreateRotateAndDisableConfidentialClient(): void
	{
		$command = new CommandTester($this->getService(CreateOAuthClientCommand::class));
		self::assertSame(
			0,
			$command->execute(['name' => 'Server integration', '--json' => true, '--scope' => ['home:write']]),
		);
		$data = TypeValidator::validateArray(Json::decode($command->getDisplay(), true));
		$id = TypeValidator::validateString($data['client_id']);
		$secret = TypeValidator::validateString($data['client_secret']);
		self::assertSame(64, strlen($secret));
		$repository = $this->getService(OAuthClientRepository::class);
		$client = $repository->getClientEntity($id);
		self::assertNotNull($client);
		self::assertNotSame($secret, $client->getSecretHash());
		self::assertTrue($repository->validateClient($id, $secret, 'client_credentials'));
		self::assertFalse($repository->validateClient($id, $secret, 'authorization_code'));
		self::assertSame(['home:write'], $client->getScopes());
		$rotate = new CommandTester($this->getService(RotateOAuthClientSecretCommand::class));
		self::assertSame(0, $rotate->execute(['client-id' => $id]));
		self::assertSame(1, preg_match('/client_secret: ([a-f0-9]{64})/', $rotate->getDisplay(), $match));
		self::assertFalse($repository->validateClient($id, $secret, 'client_credentials'));
		self::assertTrue($repository->validateClient($id, $match[1], 'client_credentials'));
		$disable = new CommandTester($this->getService(DisableOAuthClientCommand::class));
		self::assertSame(0, $disable->execute(['client-id' => $id]));
		self::assertNull($repository->getClientEntity($id));
		self::assertFalse($repository->validateClient($id, $match[1], 'client_credentials'));
	}

	public function testPublicNativeClientHasNoSecret(): void
	{
		$command = new CommandTester($this->getService(CreateOAuthClientCommand::class));
		self::assertSame(0, $command->execute([
			'name' => 'Desktop application', '--public' => true, '--json' => true,
			'--redirect-uri' => ['http://127.0.0.1:8000/callback', 'com.example.portfolio:/callback'],
		]));
		$data = TypeValidator::validateArray(Json::decode($command->getDisplay(), true));
		self::assertNull($data['client_secret']);
		$id = TypeValidator::validateString($data['client_id']);
		$client = $this->getService(OAuthClientRepository::class)->getClientEntity($id);
		self::assertNotNull($client);
		self::assertFalse($client->isConfidential());
		self::assertNull($client->getSecretHash());
		self::assertSame(['api:read'], $client->getScopes());
		self::assertTrue($client->supportsGrantType('authorization_code'));
		self::assertTrue($client->supportsGrantType('refresh_token'));
		self::assertFalse($client->supportsGrantType('client_credentials'));
		$rotate = new CommandTester($this->getService(RotateOAuthClientSecretCommand::class));
		self::assertSame(2, $rotate->execute(['client-id' => $id]));
	}

	public function testReplaceClientScopesValidatesCompleteAllowlist(): void
	{
		$create = new CommandTester($this->getService(CreateOAuthClientCommand::class));
		self::assertSame(0, $create->execute([
			'name' => 'Dashboard desktop', '--public' => true, '--json' => true,
			'--redirect-uri' => ['com.example.portfolio:/callback'],
			'--scope' => ['portfolio:read', 'market:read'],
		]));
		$data = TypeValidator::validateArray(Json::decode($create->getDisplay(), true));
		$id = TypeValidator::validateString($data['client_id']);
		$repository = $this->getService(OAuthClientRepository::class);
		$client = $repository->getClientEntity($id);
		self::assertNotNull($client);
		self::assertSame(['portfolio:read', 'market:read'], $client->getScopes());
		$update = new CommandTester($this->getService(UpdateOAuthClientScopesCommand::class));
		foreach ([[], ['unknown:scope']] as $invalid) {
			self::assertSame(2, $update->execute(['client-id' => $id, '--scope' => $invalid]));
			self::assertSame(['portfolio:read', 'market:read'], $client->getScopes());
		}

		self::assertSame(0, $update->execute(['client-id' => $id, '--scope' => ['market:read', 'market:read']]));
		self::assertSame(['market:read'], $client->getScopes());
		self::assertSame(2, $update->execute(['client-id' => 'missing-client', '--scope' => ['market:read']]));
		$disable = new CommandTester($this->getService(DisableOAuthClientCommand::class));
		self::assertSame(0, $disable->execute(['client-id' => $id]));
		self::assertSame(2, $update->execute(['client-id' => $id, '--scope' => ['portfolio:read']]));
	}

}
