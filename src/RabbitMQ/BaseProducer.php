<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use InvalidArgumentException;
use Nette\Utils\Json;

/**
 * @template TMessage of RabbitMQMessage
 */
abstract class BaseProducer
{

	public function __construct(
		private RabbitMQPublisher $publisher,
		private string $queueName,
	)
	{
	}

	/**
	 * @param TMessage $message
	 */
	final public function publish(RabbitMQMessage $message): void
	{
		$this->validateMessageType($message);
		$this->publisher->publish($this->queueName, $this->mapMessageData($message));
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
			->normalizer(Format::array());

		return Json::encode($normalizer->normalize($message));
	}

	/**
	 * @return class-string<TMessage>
	 */
	abstract protected function getMessageClass(): string;

}
