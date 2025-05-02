<?php

declare(strict_types = 1);

namespace App\Notification;

use App\Notification\RabbitMQ\NotificationMessage;
use App\Notification\RabbitMQ\NotificationProducer;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class NotificationFacade
{

	public function __construct(
		private NotificationProducer $notificationProducer,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @param array<NotificationChannelEnum> $notificationChannels
	 */
	public function create(
		NotificationTypeEnum $notificationTypeEnum,
		array $notificationChannels,
		string $message,
		NotificationParameters|null $notificationParameters = null,
	): void
	{
		$notification = new Notification(
			$notificationTypeEnum,
			$notificationChannels,
			$message,
			$this->datetimeFactory->createNow(),
		);

		if ($notificationParameters !== null) {
			foreach ($notificationParameters->getParameters() as $key => $value) {
				$notification->addParameter(NotificationParameterEnum::from($key), $value);
			}
		}

		$this->entityManager->persist($notification);
		$this->entityManager->flush();
		$this->entityManager->refresh($notification);
		$rabbitMQMessage = $notification->getRabbitMqMessage();
		assert($rabbitMQMessage instanceof NotificationMessage);
		$this->notificationProducer->publish($rabbitMQMessage);
	}

}
