<?php

declare(strict_types = 1);

namespace App\Gotenberg;

use App\UI\Extension\Webpack\WebpackAssetsFactory;
use Latte\Engine;

class GotenbergPdfDocumentRenderer
{

	private Engine $latte;

	public function __construct(private readonly WebpackAssetsFactory $webpackAssetsFactory)
	{
		$this->latte = new Engine();
	}

	/**
	 * @param string $trustedHtmlFragment HTML rendered by an application Latte template.
	 */
	public function render(string $title, string $trustedHtmlFragment): string
	{
		return $this->latte->renderToString(
			__DIR__ . '/pdfDocument.latte',
			[
				'title' => $title,
				'stylesheet' => $this->webpackAssetsFactory->getCssContents('admin'),
				'trustedHtmlFragment' => $trustedHtmlFragment,
			],
		);
	}

}
