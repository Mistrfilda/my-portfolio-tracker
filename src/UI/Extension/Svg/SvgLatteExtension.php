<?php

declare(strict_types = 1);

namespace App\UI\Extension\Svg;

use Latte\Extension;

class SvgLatteExtension extends Extension
{

	private string $svgDir;

	public function __construct(string $svgDir)
	{
		$this->svgDir = $svgDir;
	}

	/**
	 * @inheritDoc
	 */
	public function getTags(): array
	{
		return [
			'renderSvg' => [SvgNode::class, 'create'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getProviders(): array
	{
		return [
			'svgDir' => $this->svgDir,
		];
	}

}
