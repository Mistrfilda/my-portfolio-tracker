<?php

declare(strict_types = 1);

namespace App\Notification;

enum NotificationStateEnum: string
{

	case CREATED = 'created';
	case SEND_IN_PROGRESS = 'send_in_progress';
	case SENT = 'sent';

}
