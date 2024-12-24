<?php

declare(strict_types = 1);

namespace App\Notification;

interface NotificationChannelSenderFacade
{

	public function send(Notification $notification): void;

	public function getChannel(): NotificationChannelEnum;

}
