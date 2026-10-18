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
	 * @param array<string, array<string, string|null>> $userWebhooks
	 */
	public function __construct(
		private array $discordWebhooksMapping,
		private array $userWebhooks = [],
		private string $legacyRecipientUsername = 'filip',
	)
	{
	}

	public function getWebhookUrl(Notification $notification): string|null
	{
		$mapping = $this->discordWebhooksMapping;
		$recipient = $notification->getRecipient();
		if ($recipient !== null) {
			$username = $recipient->getUsername();
			if (array_key_exists($username, $this->userWebhooks)) {
				$mapping = $this->userWebhooks[$username];
			} elseif ($username !== $this->legacyRecipientUsername) {
				return null;
			}
		}

		if (array_key_exists($notification->getNotificationTypeEnum()->value, $mapping)) {
			return $mapping[$notification->getNotificationTypeEnum()->value];
		}

		// Preserve the singular key used by the original configuration.
		if ($notification->getNotificationTypeEnum() === NotificationTypeEnum::GOALS_UPDATES) {
			return $mapping['goals_update'] ?? null;
		}

		if (in_array(
			$notification->getNotificationTypeEnum(),
			[NotificationTypeEnum::PRICE_ALERT_UP, NotificationTypeEnum::PRICE_ALERT_DOWN, NotificationTypeEnum::ASSET_TRENDS],
			true,
		)) {
			$threshold = $notification->getParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD);
			if ($threshold === null) {
				return null;
			}

			$channel = sprintf(self::TREND_ALERT_MASK, $threshold);
			if (array_key_exists($channel, $mapping)) {
				return $mapping[$channel];
			}

			return $mapping[DiscordChannelEnum::TREND_ALERT_DEFAULT->value] ?? null;
		}

		return null;
	}

}
