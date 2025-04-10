<?php

declare(strict_types = 1);

namespace App\Notification;

enum NotificationTypeEnum: string
{

	case NEW_DIVIDEND = 'new_dividend';

	case PRICE_ALERT_UP = 'price_alert_up';

	case PRICE_ALERT_DOWN = 'price_alert_down';

	public function getTitle(): string
	{
		return match ($this) {
			self::NEW_DIVIDEND => 'New dividend',
			self::PRICE_ALERT_UP => 'Price alert up',
			self::PRICE_ALERT_DOWN => 'Price alert down',
		};
	}

}
