<?php

declare(strict_types = 1);

namespace App\Notification;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Notification\RabbitMQ\NotificationMessage;
use App\RabbitMQ\RabbitMQDatabaseMessage;
use App\RabbitMQ\RabbitMQMessage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('notification')]
class Notification implements Entity, RabbitMQDatabaseMessage
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\Column(type: Types::STRING, enumType: NotificationTypeEnum::class)]
	private NotificationTypeEnum $notificationTypeEnum;

	/** @var array<string> */
	#[ORM\Column(type: Types::JSON)]
	private array $notificationChannels;

	#[ORM\Column(type: Types::TEXT)]
	private string $message;

	#[ORM\Column(type: Types::STRING, enumType: NotificationStateEnum::class)]
	private NotificationStateEnum $notificationStateEnum;

	/**
	 * @param array<NotificationChannelEnum> $notificationChannels
	 */
	public function __construct(
		NotificationTypeEnum $notificationTypeEnum,
		array $notificationChannels,
		string $message,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->notificationTypeEnum = $notificationTypeEnum;
		$this->notificationChannels = array_map(
			static fn (NotificationChannelEnum $enum): string => $enum->value,
			$notificationChannels,
		);
		$this->message = $message;
		$this->notificationStateEnum = NotificationStateEnum::CREATED;
		$this->createdAt = $now;
	}

	public function sendInProgress(): void
	{
		$this->notificationStateEnum = NotificationStateEnum::SEND_IN_PROGRESS;
	}

	public function notificationSent(): void
	{
		$this->notificationStateEnum = NotificationStateEnum::SENT;
	}

	public function getRabbitMqMessage(): RabbitMQMessage
	{
		return new NotificationMessage(
			$this->id->toString(),
			$this->createdAt->getTimestamp(),
			$this->id->toString(),
		);
	}

	public function getNotificationTypeEnum(): NotificationTypeEnum
	{
		return $this->notificationTypeEnum;
	}

	/**
	 * @return array<NotificationChannelEnum>
	 */
	public function getNotificationChannels(): array
	{
		return array_map(
			static fn (string $value): NotificationChannelEnum => NotificationChannelEnum::from($value),
			$this->notificationChannels,
		);
	}

	public function getMessage(): string
	{
		return $this->message;
	}

	public function getNotificationStateEnum(): NotificationStateEnum
	{
		return $this->notificationStateEnum;
	}

}
