<?php

declare(strict_types = 1);

namespace App\Test\Unit\Gotenberg;

use App\Gotenberg\GotenbergPdfDocumentRenderer;
use App\UI\Extension\Webpack\WebpackAssetsFactory;
use PHPUnit\Framework\TestCase;

class GotenbergPdfDocumentRendererTest extends TestCase
{

	public function testRenderCreatesStyledDocumentFromTrustedHtmlFragment(): void
	{
		$webpackAssetsFactory = $this->createMock(WebpackAssetsFactory::class);
		$webpackAssetsFactory
			->expects($this->once())
			->method('getCssContents')
			->with('admin')
			->willReturn('.text-blue { color: blue; }');

		$html = (new GotenbergPdfDocumentRenderer($webpackAssetsFactory))->render(
			'Report <2026>',
			'<section><h2>Trusted content</h2></section>',
		);

		self::assertStringContainsString('<title>Report &lt;2026&gt;</title>', $html);
		self::assertStringContainsString('<h1 class="pdf-document-title">Report &lt;2026&gt;</h1>', $html);
		self::assertStringContainsString('.text-blue { color: blue; }', $html);
		self::assertStringContainsString('<section><h2>Trusted content</h2></section>', $html);
	}

}
