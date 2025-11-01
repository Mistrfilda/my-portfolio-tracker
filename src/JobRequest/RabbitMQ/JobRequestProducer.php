<?php

declare(strict_types = 1);

namespace App\JobRequest\RabbitMQ;

use App\RabbitMQ\BaseProducer;

/**
 * @extends BaseProducer<JobRequestMessage>
 */
class JobRequestProducer extends BaseProducer
{

	protected function getMessageClass(): string
	{
		return JobRequestMessage::class;
	}

}
