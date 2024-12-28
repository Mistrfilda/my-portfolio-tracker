<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Notification\Notification;
use App\Notification\NotificationChannelEnum;
use App\Notification\NotificationChannelSenderFacade;

class NotificationDiscordSenderFacade implements NotificationChannelSenderFacade
{

	/**
	 * @param array<string, string|null> $discordWebhooksMapping
	 */
	public function __construct(
		private array $discordWebhooksMapping,
		private Psr18ClientFactory $psr18ClientFactory,
		private Psr7RequestFactory $psr7RequestFactory,
	)
	{
	}

	public function send(Notification $notification): void
	{
		if (array_key_exists($notification->getNotificationTypeEnum()->value, $this->discordWebhooksMapping)) {
			$webhookUrl = $this->discordWebhooksMapping[$notification->getNotificationTypeEnum()->value];
		} else {
			$webhookUrl = $this->discordWebhooksMapping['default'];
		}

		if ($webhookUrl === null) {
			return;
		}

		$this->psr18ClientFactory->getClient()->sendRequest(
			$this->psr7RequestFactory->createPOSTRequest(
				$webhookUrl,
				[
					'content' => $notification->getMessage(),
				],
				[
					'Content-Type' => 'application/json',
				],
			),
		);
	}

	public function getChannel(): NotificationChannelEnum
	{
		return NotificationChannelEnum::DISCORD;
	}

}
