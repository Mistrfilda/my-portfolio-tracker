<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use RuntimeException;
use Symfony\Component\Process\Process;

class StockAssetScraperRunner
{

	public function __construct(
		private string $scraperDirectory,
		private string $nodeBinary = 'node',
		private int $timeoutSeconds = 180,
	)
	{
	}

	public function run(string $script, string $folder): void
	{
		if (!in_array($script, ['prices.js', 'dividends.js', 'financials.js', 'analyst.js'], true)) {
			throw new RuntimeException('Unsupported stock scraper.');
		}

		$process = new Process([
			$this->nodeBinary,
			$this->scraperDirectory . '/' . $script,
			'--data-dir', $folder,
			'--strict',
		], $this->scraperDirectory);
		$process->setTimeout($this->timeoutSeconds);
		$output = '';
		$process->run(static function (string $type, string $buffer) use ($process, &$output): void {
			$output = substr($output . $buffer, -4096);
			$process->clearOutput();
			$process->clearErrorOutput();
		});
		if (!$process->isSuccessful()) {
			throw new RuntimeException(
				sprintf('Stock scraper %s failed (exit code %s): %s', $script, $process->getExitCode(), $output),
			);
		}
	}

}
