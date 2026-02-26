<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

enum DiscordChannelEnum: string
{

	case NEW_DIVIDEND = 'new_dividend';
	case TREND_ALERT_DEFAULT = 'trend_alert_default';
	case TREND_ALERT_1_DAY = 'trend_alert_1_days';
	case TREND_ALERT_7_DAYS = 'trend_alert_7_days';
	case TREND_ALERT_30_DAYS = 'trend_alert_30_days';
	case GOALS_UPDATES = 'goals_updates';

}
