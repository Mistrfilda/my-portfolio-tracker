<?php

declare(strict_types = 1);

namespace App\Utils\Monolog;

use Monolog\Level;
use Monolog\LogRecord;
use Throwable;
use Tracy\ILogger;
use Tracy\Logger;

class TracyExceptionFileProcessor
{

	private Logger $tracyLogger;

	public function __construct(string $logDir,)
	{
		$this->tracyLogger = new Logger($logDir, null, null);
	}

	private function getTracyLevel(Level $level): string
	{
		return match ($level) {
			Level::Emergency, Level::Alert, Level::Critical => ILogger::CRITICAL,
			Level::Warning, Level::Notice => ILogger::WARNING,
			Level::Info => ILogger::INFO,
			Level::Debug => ILogger::DEBUG,
			default => ILogger::ERROR,
		};
	}

	public function __invoke(LogRecord $record): LogRecord
	{
		$exception = $record->context['exception'] ?? null;
		if (!$exception instanceof Throwable || str_contains($record->message, ' @@ ')) {
			return $record;
		}

		$exceptionFile = $this->tracyLogger->getExceptionFile($exception, $this->getTracyLevel($record->level));

		return $record->with(message: sprintf('%s  @@  %s', $record->message, basename($exceptionFile)));
	}

}
