<?php

declare(strict_types = 1);

namespace App\Notification;

use App\Admin\AppAdmin;
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
	 * Create a shared market notification containing no personal portfolio data.
	 *
	 * @param array<NotificationChannelEnum> $notificationChannels
	 * @param array<string, mixed> $data
	 */
	public function create(
		NotificationTypeEnum $notificationTypeEnum,
		array $notificationChannels,
		string $message,
		NotificationParameters|null $notificationParameters = null,
		array $data = [],
	): void
	{
		$notification = new Notification(
			$notificationTypeEnum,
			$notificationChannels,
			$message,
			$this->datetimeFactory->createNow(),
			$data,
		);

		$this->persistAndPublish($notification, $notificationParameters);
	}

	/**
	 * @param array<NotificationChannelEnum> $notificationChannels
	 * @param array<string, mixed> $data
	 */
	public function createForRecipient(
		AppAdmin $recipient,
		NotificationTypeEnum $notificationTypeEnum,
		array $notificationChannels,
		string $message,
		NotificationParameters|null $notificationParameters = null,
		array $data = [],
	): void
	{
		$notification = new Notification(
			$notificationTypeEnum,
			$notificationChannels,
			$message,
			$this->datetimeFactory->createNow(),
			$data,
			$recipient,
		);

		$this->persistAndPublish($notification, $notificationParameters);
	}

	private function persistAndPublish(
		Notification $notification,
		NotificationParameters|null $notificationParameters,
	): void
	{
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
