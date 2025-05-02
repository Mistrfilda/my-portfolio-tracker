<?php

declare(strict_types = 1);


namespace App\Test\Unit\Notification;

use App\Notification\Notification;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationChannelSenderFacade;
use App\Notification\NotificationRepository;
use App\Notification\NotificationSenderFacade;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class NotificationSenderFacadeTest extends TestCase
{
	private NotificationRepository $notificationRepository;
	private EntityManagerInterface $entityManager;
	private NotificationChannelSenderFacade $discordChannelFacade;
	private NotificationSenderFacade $notificationSenderFacade;

	protected function setUp(): void
	{
		$this->notificationRepository = $this->createMock(NotificationRepository::class);
		$this->entityManager = $this->createMock(EntityManagerInterface::class);
		$this->discordChannelFacade = $this->createMock(NotificationChannelSenderFacade::class);

		$this->discordChannelFacade
			->method('getChannel')
			->willReturn(NotificationChannelEnum::DISCORD);

		$this->notificationSenderFacade = new NotificationSenderFacade(
			[$this->discordChannelFacade],
			$this->notificationRepository,
			$this->entityManager
		);
	}

	public function testProcessSendsNotificationToDiscordChannel(): void
	{
		$notificationId = Uuid::uuid4()->toString();
		$notification = $this->createMock(Notification::class);

		$notification->method('getNotificationChannels')
			->willReturn([NotificationChannelEnum::DISCORD]);

		$this->notificationRepository
			->expects(self::once())
			->method('getById')
			->with(Uuid::fromString($notificationId))
			->willReturn($notification);

		$notification->expects(self::once())->method('sendInProgress');
		$notification->expects(self::once())->method('notificationSent');

		$this->entityManager->expects(self::exactly(2))->method('flush');
		$this->discordChannelFacade->expects(self::once())
			->method('send')
			->with($notification);
		$this->notificationSenderFacade->process($notificationId);
	}

	public function testProcessSkipsUnknownChannels(): void
	{
		$notificationId = Uuid::uuid4()->toString();
		$notification = $this->createMock(Notification::class);

		$notification->method('getNotificationChannels')
			->willReturn([]);

		$this->notificationRepository
			->expects(self::once())
			->method('getById')
			->with(Uuid::fromString($notificationId))
			->willReturn($notification);

		$notification->expects(self::once())->method('sendInProgress');
		$notification->expects(self::once())->method('notificationSent');
		$this->entityManager->expects(self::exactly(2))->method('flush');
		$this->discordChannelFacade->expects(self::never())->method('send');
		$this->notificationSenderFacade->process($notificationId);
	}
}

