<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Extension\Webpack;

use App\UI\Extension\Webpack\WebpackAssetsFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class WebpackAssetsFactoryTest extends TestCase
{

	public function testGetCssContentsReadsVersionedEntryPointAssets(): void
	{
		$assetsDir = sys_get_temp_dir() . '/webpack-assets-' . bin2hex(random_bytes(8));
		FileSystem::createDir($assetsDir);

		try {
			FileSystem::write(
				$assetsDir . '/entrypoints.json',
				Json::encode([
					'entrypoints' => [
						'admin' => [
							'css' => ['/build/admin/admin.abc123.css'],
						],
					],
				]),
			);
			FileSystem::write($assetsDir . '/admin.abc123.css', '.report { color: blue; }');

			$factory = new WebpackAssetsFactory([$assetsDir]);

			self::assertSame('.report { color: blue; }', $factory->getCssContents('admin'));
		} finally {
			FileSystem::delete($assetsDir);
		}
	}

}
