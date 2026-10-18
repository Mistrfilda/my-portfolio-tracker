<?php

declare(strict_types = 1);

namespace App\Notification;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Notification\RabbitMQ\NotificationMessage;
use App\RabbitMQ\RabbitMQDatabaseMessage;
use App\RabbitMQ\RabbitMQMessage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('notification')]
class Notification implements Entity, RabbitMQDatabaseMessage
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: true)]
	private AppAdmin|null $recipient;

	#[ORM\Column(type: Types::STRING, enumType: NotificationTypeEnum::class)]
	private NotificationTypeEnum $notificationTypeEnum;

	/** @var array<string> */
	#[ORM\Column(type: Types::JSON)]
	private array $notificationChannels;

	#[ORM\Column(type: Types::TEXT)]
	private string $message;

	#[ORM\Column(type: Types::STRING, enumType: NotificationStateEnum::class)]
	private NotificationStateEnum $notificationStateEnum;

	/** @var array<string, string|int> */
	#[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
	private array $parameters;

	/** @var array<string, mixed> */
	#[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
	private array $data;

	/**
	 * @param array<NotificationChannelEnum> $notificationChannels
	 * @param array<string, mixed> $data
	 */
	public function __construct(
		NotificationTypeEnum $notificationTypeEnum,
		array $notificationChannels,
		string $message,
		ImmutableDateTime $now,
		array $data = [],
		AppAdmin|null $recipient = null,
	)
	{
		if ($notificationTypeEnum === NotificationTypeEnum::GOALS_UPDATES && $recipient === null) {
			throw new InvalidArgumentException('Goal notifications require a recipient.');
		}

		$this->id = Uuid::uuid4();
		$this->recipient = $recipient;
		$this->notificationTypeEnum = $notificationTypeEnum;
		$this->notificationChannels = array_map(
			static fn (NotificationChannelEnum $enum): string => $enum->value,
			$notificationChannels,
		);
		$this->message = $message;
		$this->notificationStateEnum = NotificationStateEnum::CREATED;
		$this->createdAt = $now;
		$this->parameters = [];
		$this->data = $data;
	}

	public function addParameter(NotificationParameterEnum $key, string|int $value): void
	{
		$this->parameters[$key->value] = $value;
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
	 * Null denotes a shared market notification. Personal recipients cannot be reassigned.
	 */
	public function getRecipient(): AppAdmin|null
	{
		return $this->recipient;
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

	public function getParameter(NotificationParameterEnum $key): string|int|null
	{
		return $this->parameters[$key->value] ?? null;
	}

	/**
	 * @return array<string, string|int>
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getData(): array
	{
		return $this->data;
	}

}
