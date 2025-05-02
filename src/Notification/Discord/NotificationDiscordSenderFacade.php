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

	public function __construct(
		private Psr18ClientFactory $psr18ClientFactory,
		private Psr7RequestFactory $psr7RequestFactory,
		private DiscordMessageService $discordMessageService,
		private DiscordChannelService $discordChannelService,
	)
	{
	}

	public function send(Notification $notification): void
	{
		$webhookUrl = $this->discordChannelService->getWebhookUrl($notification);

		if ($webhookUrl === null) {
			return;
		}

		$this->psr18ClientFactory->getClient()->sendRequest(
			$this->psr7RequestFactory->createPOSTRequest(
				$webhookUrl,
				$this->discordMessageService->getMessage($notification),
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
