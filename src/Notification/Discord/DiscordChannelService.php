<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationTypeEnum;

class DiscordChannelService
{

	private const TREND_ALERT_MASK = 'trend_alert_%s_days';

	/**
	 * @param array<string, string|null> $discordWebhooksMapping
	 */
	public function __construct(private array $discordWebhooksMapping)
	{
	}

	public function getWebhookUrl(Notification $notification): string|null
	{
		if (array_key_exists($notification->getNotificationTypeEnum()->value, $this->discordWebhooksMapping)) {
			return $this->discordWebhooksMapping[$notification->getNotificationTypeEnum()->value];
		}

		if (in_array(
			$notification->getNotificationTypeEnum(),
			[NotificationTypeEnum::PRICE_ALERT_UP, NotificationTypeEnum::PRICE_ALERT_DOWN],
			true,
		)) {
			$threshold = $notification->getParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD);
			if ($threshold === null) {
				return null;
			}

			$channel = sprintf(self::TREND_ALERT_MASK, $threshold);
			if (array_key_exists($channel, $this->discordWebhooksMapping)) {
				return $this->discordWebhooksMapping[$channel];
			}

			return $this->discordWebhooksMapping[DiscordChannelEnum::TREND_ALERT_DEFAULT->value];
		}

		return null;
	}

}
