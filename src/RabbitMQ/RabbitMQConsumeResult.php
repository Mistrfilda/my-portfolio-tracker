<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

enum RabbitMQConsumeResult: string
{

	case Ack = 'ack';
	case Nack = 'nack';
	case Reject = 'reject';

}
