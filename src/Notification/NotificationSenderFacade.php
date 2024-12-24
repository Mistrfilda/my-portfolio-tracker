<?php

declare(strict_types = 1);

namespace App\Notification;

use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class NotificationSenderFacade
{

	/**
	 * @param array<NotificationChannelSenderFacade> $notificationChannelSenderFacades
	 */
	public function __construct(
		private array $notificationChannelSenderFacades,
		private NotificationRepository $notificationRepository,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	public function process(string $notificationId): void
	{
		$notification = $this->notificationRepository->getById(Uuid::fromString($notificationId));
		$notification->sendInProgress();
		$this->entityManager->flush();

		foreach ($this->notificationChannelSenderFacades as $notificationChannelSenderFacade) {
			if (
				in_array($notificationChannelSenderFacade->getChannel(), $notification->getNotificationChannels(), true)
			) {
				$notificationChannelSenderFacade->send($notification);
			}
		}

		$notification->notificationSent();
		$this->entityManager->flush();
	}

}
