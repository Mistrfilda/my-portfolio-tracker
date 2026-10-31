<?php

declare(strict_types = 1);

namespace App\Test\Integration\Notification;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Gotenberg\GotenbergScreenshotService;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Notification\Discord\AssetTrendReportRenderer;
use App\Notification\Discord\DiscordChannelService;
use App\Notification\Discord\DiscordMessageService;
use App\Notification\Discord\NotificationDiscordSenderFacade;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationRepository;
use App\Notification\NotificationSenderFacade;
use App\Notification\NotificationStateEnum;
use App\Notification\NotificationTypeEnum;
use App\Notification\RabbitMQ\NotificationProducer;
use App\Test\Integration\IntegrationTestCase;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Amqp\RabbitMQPublisher;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class NotificationDeliveryOwnershipTest extends IntegrationTestCase
{

	public function testPersistedRecipientSurvivesQueueRetryAndDifferentCurrentUser(): void
	{
		$em = $this->getService(EntityManagerInterface::class);
		$owners = [];
		foreach (['alice', 'bob'] as $name) {
			$username = $name . '-' . Uuid::uuid4()->toString();
			$owner = new AppAdmin(
				$name,
				$username,
				$username . '@example.test',
				'unused',
				new ImmutableDateTime(),
				false,
				false,
			);
			$em->persist($owner);
			$owners[] = $owner;
		}

		$em->flush();
		$usernames = array_map(static fn (AppAdmin $owner): string => $owner->getUsername(), $owners);
		$ownerIds = array_map(static fn (AppAdmin $owner): string => $owner->getId()->toString(), $owners);
		$queuedIds = [];
		$publisher = $this->createMock(RabbitMQPublisher::class);
		$publisher->expects($this->exactly(3))->method('publish')->willReturnCallback(
			static function (string $queue, string $payload) use (&$queuedIds): void {
				self::assertSame('test-notifications', $queue);
				$data = TypeValidator::validateArray(Json::decode($payload, forceArrays: true));
				self::assertSame(['requestId', 'messageQueuedAtTimestamp', 'notificationId'], array_keys($data));
				$queuedIds[] = TypeValidator::validateString($data['notificationId']);
			},
		);
		$facade = new NotificationFacade(
			new NotificationProducer($publisher, 'test-notifications'),
			$em,
			$this->getDatetimeFactory(),
		);
		foreach ($owners as $owner) {
			$facade->createForRecipient(
				$owner,
				NotificationTypeEnum::GOALS_UPDATES,
				[NotificationChannelEnum::DISCORD],
				$owner->getName(),
			);
		}

		$facade->create(NotificationTypeEnum::NEW_DIVIDEND, [NotificationChannelEnum::DISCORD], 'Shared dividend');
		$em->clear();
		$repository = $this->getService(NotificationRepository::class);
		foreach ($ownerIds as $index => $ownerId) {
			$notification = $repository->getById(Uuid::fromString($queuedIds[$index]));
			self::assertSame($ownerId, $notification->getRecipient()?->getId()->toString());
		}

		self::assertNull($repository->getById(Uuid::fromString($queuedIds[2]))->getRecipient());
		$channelService = new DiscordChannelService(
			['new_dividend' => 'https://example.test/shared'],
			[$usernames[0] => ['goals_updates' => 'https://example.test/alice'],
				$usernames[1] => ['goals_updates' => 'https://example.test/bob']],
		);
		$requests = [];
		$client = $this->createMock(ClientInterface::class);
		$client->expects($this->exactly(4))->method('sendRequest')->willReturnCallback(
			static function (RequestInterface $request) use (&$requests): ResponseInterface {
				$requests[] = (string) $request->getUri();
				return new Response(count($requests) === 1 ? 500 : 204);
			},
		);
		$clientFactory = $this->createStub(Psr18ClientFactory::class);
		$clientFactory->method('getClient')->willReturn($client);
		$renderer = $this->createMock(AssetTrendReportRenderer::class);
		$renderer->expects($this->never())->method('render');
		$gotenberg = $this->createMock(GotenbergScreenshotService::class);
		$gotenberg->expects($this->never())->method('captureHtml');
		$discord = new NotificationDiscordSenderFacade(
			$clientFactory,
			new Psr7RequestFactory(),
			new DiscordMessageService($this->getDatetimeFactory()),
			$channelService,
			$renderer,
			$gotenberg,
		);
		$sender = new NotificationSenderFacade([$discord], $repository, $em);
		try {
			$sender->process($queuedIds[0]);
			self::fail('First delivery must fail to exercise the retry.');
		} catch (RuntimeException $exception) {
			self::assertSame('Discord webhook request failed with HTTP 500.', $exception->getMessage());
		}

		$em->clear();
		$pending = $repository->getById(Uuid::fromString($queuedIds[0]));
		self::assertSame(NotificationStateEnum::SEND_IN_PROGRESS, $pending->getNotificationStateEnum());
		self::assertSame($ownerIds[0], $pending->getRecipient()?->getId()->toString());
		$this->getService(CurrentAppAdminGetter::class)->setApiAppAdmin($owners[1]);
		$sender->process($queuedIds[1]);
		$sender->process($queuedIds[0]);
		$sender->process($queuedIds[2]);
		$em->clear();
		foreach ($queuedIds as $id) {
			self::assertSame(
				NotificationStateEnum::SENT,
				$repository->getById(Uuid::fromString($id))->getNotificationStateEnum(),
			);
			$sender->process($id);
		}

		self::assertSame([
			'https://example.test/alice', 'https://example.test/bob', 'https://example.test/alice', 'https://example.test/shared',
		], $requests);
	}

}
