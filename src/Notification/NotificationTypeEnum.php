<?php

declare(strict_types = 1);

namespace App\Notification;

enum NotificationTypeEnum: string
{

	case NEW_DIVIDEND = 'new_dividend';

	case PRICE_ALERT_UP = 'price_alert_up';

	case PRICE_ALERT_DOWN = 'price_alert_down';

}
