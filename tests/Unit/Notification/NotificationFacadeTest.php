<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Admin\AppAdmin;
use App\Notification\Notification;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationTypeEnum;
use App\Notification\RabbitMQ\NotificationProducer;
use App\RabbitMQ\RabbitMQPublisher;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class NotificationFacadeTest extends TestCase
{

	public function testCreatePersistsStructuredData(): void
	{
		$rabbitMQPublisher = $this->createMock(RabbitMQPublisher::class);
		$notificationProducer = new NotificationProducer($rabbitMQPublisher, 'notificationQueue');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory
			->method('createNow')
			->willReturn(new ImmutableDateTime('2026-07-14 18:30:00'));

		$data = [
			'trends' => [
				[
					'name' => 'Test Asset',
					'currentPrice' => 100.0,
					'currency' => 'CZK',
					'trend' => 3.0,
				],
			],
		];

		$entityManager
			->expects($this->once())
			->method('persist')
			->with($this->callback(static function (Notification $notification) use ($data): bool {
				self::assertSame('Asset trends', $notification->getMessage());
				self::assertSame($data, $notification->getData());
				self::assertNull($notification->getRecipient());

				return true;
			}));
		$entityManager
			->expects($this->once())
			->method('flush');
		$entityManager
			->expects($this->once())
			->method('refresh')
			->with($this->isInstanceOf(Notification::class));
		$rabbitMQPublisher
			->expects($this->once())
			->method('publish')
			->with(
				'notificationQueue',
				$this->callback(static fn (string $payload): bool => $payload !== ''),
			);

		$notificationFacade = new NotificationFacade(
			$notificationProducer,
			$entityManager,
			$datetimeFactory,
		);
		$notificationFacade->create(
			NotificationTypeEnum::ASSET_TRENDS,
			[NotificationChannelEnum::DISCORD],
			'Asset trends',
			data: $data,
		);
	}

	public function testPersonalRecipientIsPersistedBeforePublishingCompatibleQueueMessage(): void
	{
		$recipient = $this->createStub(AppAdmin::class);
		$publisher = $this->createMock(RabbitMQPublisher::class);
		$em = $this->createMock(EntityManagerInterface::class);
		$datetime = $this->createStub(DatetimeFactory::class);
		$now = new ImmutableDateTime('2026-10-18');
		$datetime->method('createNow')->willReturn($now);
		$saved = null;
		$flushed = false;
		$em->expects($this->once())->method('persist')->willReturnCallback(
			static function (Notification $notification) use (&$saved, $recipient): void {
				self::assertSame($recipient, $notification->getRecipient());
				$saved = $notification;
			},
		);
		$em->expects($this->once())->method('flush')->willReturnCallback(static function () use (&$flushed): void {
			$flushed = true;
		});
		$em->expects($this->once())->method('refresh');
		$publisher->expects($this->once())->method('publish')->willReturnCallback(
			static function (string $queue, string $payload) use (&$saved, &$flushed, $now): void {
				self::assertTrue($flushed);
				self::assertInstanceOf(Notification::class, $saved);
				self::assertSame('notificationQueue', $queue);
				self::assertSame([
					'requestId' => $saved->getId()->toString(),
					'messageQueuedAtTimestamp' => $now->getTimestamp(),
					'notificationId' => $saved->getId()->toString(),
				], Json::decode($payload, forceArrays: true));
			},
		);
		$facade = new NotificationFacade(new NotificationProducer($publisher, 'notificationQueue'), $em, $datetime);
		$facade->createForRecipient(
			$recipient,
			NotificationTypeEnum::GOALS_UPDATES,
			[NotificationChannelEnum::DISCORD],
			'Goal',
		);
	}

	public function testPersonalGoalCannotBeCreatedWithoutRecipient(): void
	{
		$publisher = $this->createMock(RabbitMQPublisher::class);
		$publisher->expects($this->never())->method('publish');
		$em = $this->createMock(EntityManagerInterface::class);
		$em->expects($this->never())->method('persist');
		$datetime = $this->createStub(DatetimeFactory::class);
		$datetime->method('createNow')->willReturn(new ImmutableDateTime());
		$facade = new NotificationFacade(new NotificationProducer($publisher, 'notificationQueue'), $em, $datetime);
		$this->expectException(InvalidArgumentException::class);
		$facade->create(NotificationTypeEnum::GOALS_UPDATES, [NotificationChannelEnum::DISCORD], 'Goal');
	}

}
