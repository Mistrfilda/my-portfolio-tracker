<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use InvalidArgumentException;
use Nette\Utils\Json;

/**
 * @template TMessage of RabbitMQMessage
 */
abstract class BaseConsumer implements RabbitMQConsumerHandler
{

	public function consume(string $payload): RabbitMQConsumeResult
	{
		$messageObject = $this->mapMessageData($payload);
		$this->processMessage($messageObject);
		return RabbitMQConsumeResult::Ack;
	}

	/**
	 * @return TMessage
	 */
	private function mapMessageData(string $json): RabbitMQMessage
	{
		$data = Json::decode($json, forceArrays: true);

		if (!is_array($data)) {
			throw new InvalidArgumentException('RabbitMQ message payload must be a JSON object.');
		}

		$mapper = new MapperBuilder()->allowPermissiveTypes()->mapper();
		return $mapper->map($this->getMessageClass(), Source::array($data));
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
