<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use Bunny\Message;
use Contributte\RabbitMQ\Consumer\IConsumer;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;

/**
 * @template TMessage of RabbitMQMessage
 */
abstract class BaseConsumer implements IConsumer
{

	public function consume(Message $message): int
	{
		$messageObject = $this->mapMessageData($message->content);
		$this->processMessage($messageObject);
		return IConsumer::MESSAGE_ACK;
	}

	/**
	 * @return TMessage
	 */
	private function mapMessageData(string $json): RabbitMQMessage
	{
		$mapper = (new MapperBuilder())->allowPermissiveTypes()->mapper();
		return $mapper->map($this->getMessageClass(), Source::json($json));
	}

	/**
	 * @param TMessage $messageObject
	 */
	abstract protected function processMessage(RabbitMQMessage $messageObject): void;

	/**
	 * @return class-string<TMessage>
	 */
	abstract protected function getMessageClass(): string;

}
