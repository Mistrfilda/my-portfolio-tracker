<?php

declare(strict_types = 1);

namespace App\UI\Extension\Webpack;

use Latte\Extension;

final class WebpackLatteExtension extends Extension
{

	private WebpackAssetsFactory $assetLocator;

	public function __construct(WebpackAssetsFactory $assetLocator)
	{
		$this->assetLocator = $assetLocator;
	}

	/**
	 * @inheritDoc
	 */
	public function getTags(): array
	{
		return [
			'webpackJs' => [WebpackEncoreJsNode::class, 'create'],
			'webpackCss' => [WebpackEncoreCssNode::class, 'create'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getProviders(): array
	{
		return [
			'webpackEncoreTagRenderer' => $this->assetLocator,
		];
	}

}
