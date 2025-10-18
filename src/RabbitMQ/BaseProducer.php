<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use Contributte\RabbitMQ\Producer\Producer;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use InvalidArgumentException;

/**
 * @template TMessage of RabbitMQMessage
 */
abstract class BaseProducer
{

	public function __construct(private Producer $producer)
	{
	}

	/**
	 * @param TMessage $message
	 */
	final public function publish(RabbitMQMessage $message): void
	{
		$this->validateMessageType($message);
		$this->producer->publish($this->mapMessageData($message));
		$this->producer->sendHeartbeat();
	}

	private function validateMessageType(RabbitMQMessage $message): void
	{
		$expectedClass = $this->getMessageClass();
		if (!$message instanceof $expectedClass) {
			throw new InvalidArgumentException(
				sprintf(
					'Message must be an instance of %s, %s given.',
					$expectedClass,
					$message::class,
				),
			);
		}
	}

	/**
	 * @param TMessage $message
	 */
	private function mapMessageData(RabbitMQMessage $message): string
	{
		$normalizer = new NormalizerBuilder()
			->normalizer(Format::json());

		return $normalizer->normalize($message);
	}

	/**
	 * @return class-string<TMessage>
	 */
	abstract protected function getMessageClass(): string;

}
