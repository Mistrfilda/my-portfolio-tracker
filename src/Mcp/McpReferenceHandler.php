<?php

declare(strict_types = 1);

namespace App\Mcp;

use App\Mcp\Support\McpInvalidInputException;
use Mcp\Server\Capability\Registry\ElementReference;
use Mcp\Server\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Server\Exception\ToolCallException;
use Throwable;

final readonly class McpReferenceHandler implements ReferenceHandlerInterface
{

	public function __construct(private ReferenceHandlerInterface $inner,)
	{
	}

	/**
	 * @param array<string, mixed> $arguments
	 */
	public function handle(ElementReference $reference, array $arguments): mixed
	{
		try {
			return $this->inner->handle($reference, $arguments);
		} catch (Throwable $e) {
			if ($e instanceof McpInvalidInputException) {
				throw new ToolCallException($e->getMessage(), 0, $e);
			}

			throw $e;
		}
	}

}
