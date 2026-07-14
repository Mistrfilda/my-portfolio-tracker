<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Notification\Notification;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationFacade;
use App\Notification\NotificationTypeEnum;
use App\Notification\RabbitMQ\NotificationProducer;
use App\RabbitMQ\RabbitMQPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
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

}
