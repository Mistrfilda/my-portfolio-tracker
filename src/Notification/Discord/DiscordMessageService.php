<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Notification\Notification;
use App\Notification\NotificationTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;

class DiscordMessageService
{

	public function __construct(private DatetimeFactory $datetimeFactory)
	{

	}

	/**
	 * @return array<mixed>
	 */
	public function getMessage(Notification $notification): array
	{
		return [
			'embeds' => [
				[
					'title' => $this->getTitle($notification->getNotificationTypeEnum()),
					'description' => $notification->getMessage(),
					'color' => $this->getColor($notification->getNotificationTypeEnum()),
					'timestamp' => $this->datetimeFactory->createNow()->format('Y-m-d\TH:i:s.u\Z'),
				],
			],
		];
	}

	private function getTitle(NotificationTypeEnum $type): string
	{
		return match ($type) {
			NotificationTypeEnum::NEW_DIVIDEND => 'Nová dividenda',
			NotificationTypeEnum::PRICE_ALERT_UP => '📈 Price alert up',
			NotificationTypeEnum::PRICE_ALERT_DOWN => '📉 Price alert down',
			NotificationTypeEnum::GOALS_UPDATES => 'Aktualizace cíle portfolia',
		};
	}

	private function getColor(NotificationTypeEnum $type): int
	{
		return match ($type) {
			NotificationTypeEnum::NEW_DIVIDEND, NotificationTypeEnum::PRICE_ALERT_UP, NotificationTypeEnum::GOALS_UPDATES => 3066993,
			NotificationTypeEnum::PRICE_ALERT_DOWN => 15158332,
		};
	}

}
