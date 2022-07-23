<?php

declare(strict_types = 1);

namespace App\UI\Extension\Webpack;

use Nette\Utils\FileSystem;
use Nette\Utils\Html;
use Nette\Utils\Json;
use function array_key_exists;
use function count;
use function implode;
use function sprintf;

class WebpackAssetsFactory
{

	private const ENTRYPOINT_NAME = 'entrypoints.json';

	/** @var array<string> */
	private array $loadedAssets = [];

	/**
	 * @param array<string> $assetsDirs
	 */
	public function __construct(private array $assetsDirs)
	{
	}

	public function getCssAssets(string $entryName): string
	{
		$assets = $this->loadAssets();
		if (array_key_exists($entryName, $assets) === false) {
			throw new WebpackException(
				sprintf('Unknown entry name %s', $entryName),
			);
		}

		if (array_key_exists('css', $assets[$entryName]) === false) {
			throw new WebpackException(
				sprintf('Missing css assets for entry %s', $entryName),
			);
		}

		$cssAssets = [];
		foreach ($assets[$entryName]['css'] as $cssAsset) {
			$link = Html::el('link')->addAttributes(
				[
					'rel' => 'stylesheet',
					'href' => $cssAsset,
				],
			);

			$cssAssets[] = $link->render();
		}

		return implode('', $cssAssets);
	}

	public function getJsAssets(string $entryName): string
	{
		$assets = $this->loadAssets();
		if (array_key_exists($entryName, $assets) === false) {
			throw new WebpackException(
				sprintf('Unknown entry name %s', $entryName),
			);
		}

		if (array_key_exists('js', $assets[$entryName]) === false) {
			throw new WebpackException(
				sprintf('Missing js assets for entry %s', $entryName),
			);
		}

		$jsAssets = [];
		foreach ($assets[$entryName]['js'] as $jsAsset) {
			$script = Html::el('script')->addAttributes(
				[
					'src' => $jsAsset,
					'type' => 'text/javascript',
				],
			);

			$jsAssets[] = $script->render();
		}

		return implode('', $jsAssets);
	}

	/**
	 * @return array<mixed>
	 */
	private function loadAssets(): array
	{
		if (count($this->loadedAssets) > 0) {
			return $this->loadedAssets;
		}

		foreach ($this->assetsDirs as $assetDir) {
			$entryPointContents = FileSystem::read($assetDir . '/' . self::ENTRYPOINT_NAME);
			$decodedContents = Json::decode($entryPointContents, Json::FORCE_ARRAY);

			if (is_array($decodedContents) === false || !array_key_exists('entrypoints', $decodedContents)) {
				throw new WebpackException('Missing entrypoints');
			}

			foreach ($decodedContents['entrypoints'] as $entryPointName => $contents) {
				if (array_key_exists($entryPointName, $this->loadedAssets)) {
					throw new WebpackException(
						sprintf('Duplicate entry name %s', $entryPointName),
					);
				}

				$this->loadedAssets[$entryPointName] = $contents;
			}
		}

		return $this->loadedAssets;
	}

}
