<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Download;

use App\Stock\Asset\Download\StockAssetScraperRunner;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class StockAssetScraperRunnerTest extends TestCase
{

	private string $folder;

	protected function setUp(): void
	{
		$this->folder = sys_get_temp_dir() . '/stock-runner-test-' . Uuid::uuid4();
		FileSystem::createDir($this->folder);
	}

	protected function tearDown(): void
	{
		FileSystem::delete($this->folder);
	}

	public function testNodeReceivesIsolatedPathAsOneArgument(): void
	{
		FileSystem::write($this->folder . '/prices.js', <<<'JS'
			const fs = require('node:fs');
			fs.writeFileSync('arguments.json', JSON.stringify(process.argv.slice(2)));
			JS);
		$path = $this->folder . '/data with spaces; $(not-a-command)';
		(new StockAssetScraperRunner($this->folder))->run('prices.js', $path);
		$this->assertSame(
			['--data-dir', $path, '--strict'],
			Json::decode(FileSystem::read($this->folder . '/arguments.json'), true),
		);
	}

	public function testNonzeroExitIncludesFailureDetails(): void
	{
		FileSystem::write($this->folder . '/prices.js', 'console.error("fixture failed"); process.exit(4);');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('exit code 4');
		(new StockAssetScraperRunner($this->folder))->run('prices.js', $this->folder);
	}

	public function testProcessHasBoundedRuntime(): void
	{
		FileSystem::write($this->folder . '/prices.js', 'setTimeout(() => {}, 30000);');
		$this->expectException(ProcessTimedOutException::class);
		(new StockAssetScraperRunner($this->folder, timeoutSeconds: 1))->run('prices.js', $this->folder);
	}

	public function testArbitraryScriptsCannotBeRun(): void
	{
		$this->expectException(RuntimeException::class);
		(new StockAssetScraperRunner($this->folder))->run('../other.js', $this->folder);
	}

	public function testScraperUsesOwnFilesAndDiscardsOldTemporaryResults(): void
	{
		$base = Json::encode(dirname(__DIR__, 5) . '/puppeter/PuppeteerScraperBase.js');
		FileSystem::write(
			$this->folder . '/fixture.mjs',
			'import { PuppeteerScraperBase } from ' . $base . ';' . <<<'JS'
			class Fixture extends PuppeteerScraperBase {
				async processData(entries) {
					return entries.filter(entry => !entry.fail).map(entry => ({...entry, downloadedAt: Math.floor(Date.now() / 1000)}));
				}
			}
			await new Fixture().run('prices.json', 'prices.json');
			JS,
		);
		$processes = [];
		foreach (['first', 'second'] as $name) {
			$folder = $this->folder . '/' . $name;
			FileSystem::write($folder . '/requests/prices.json', Json::encode([['id' => 'selected', 'name' => $name]]));
			FileSystem::write($folder . '/results/temp_prices.json', Json::encode([
				['id' => 'selected', 'name' => 'stale', 'downloadedAt' => 1],
				['id' => 'foreign', 'name' => 'unexpected', 'downloadedAt' => time()],
			]));
			$process = new Process(['node', $this->folder . '/fixture.mjs', '--data-dir', $folder, '--strict']);
			$process->setTimeout(10);
			$process->start();
			$processes[$name] = $process;
		}

		foreach ($processes as $name => $process) {
			$this->assertSame(0, $process->wait(), $process->getErrorOutput());
			$folder = $this->folder . '/' . $name;
			$result = Json::decode(FileSystem::read($folder . '/results/prices.json'), true);
			$this->assertCount(1, $result);
			$this->assertSame($name, $result[0]['name']);
			$this->assertGreaterThan(1, $result[0]['downloadedAt']);
			$this->assertFileDoesNotExist($folder . '/results/temp_prices.json');
		}

		$folder = $this->folder . '/failure';
		FileSystem::write($folder . '/requests/prices.json', Json::encode([['id' => 'selected', 'fail' => true]]));
		FileSystem::createDir($folder . '/results');
		$process = new Process(['node', $this->folder . '/fixture.mjs', '--data-dir', $folder, '--strict']);
		$process->setTimeout(10);
		$this->assertNotSame(0, $process->run());
		$this->assertStringContainsString('Requested stock data could not be downloaded', $process->getErrorOutput());
	}

}
