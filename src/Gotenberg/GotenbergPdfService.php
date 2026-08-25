<?php

declare(strict_types = 1);

namespace App\Gotenberg;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use RuntimeException;

class GotenbergPdfService
{

	private const string PDF_PATH = '/forms/chromium/convert/html';

	private const string HEADER_HTML = <<<'HTML'
<!doctype html>
<html lang="cs">
<head>
	<meta charset="utf-8">
	<style>
		html {
			-webkit-print-color-adjust: exact;
		}

		body {
			color: #64748b;
			font-family: Arial, sans-serif;
			font-size: 9px;
			margin: 0;
			width: 100%;
		}

		.header {
			border-bottom: 1px solid #e2e8f0;
			box-sizing: border-box;
			padding: 0 11mm 2mm;
			text-align: right;
			width: 100%;
		}
	</style>
</head>
<body>
	<div class="header">My Portfolio Tracker</div>
</body>
</html>
HTML;

	private const string FOOTER_HTML = <<<'HTML'
<!doctype html>
<html lang="cs">
<head>
	<meta charset="utf-8">
	<style>
		html {
			-webkit-print-color-adjust: exact;
		}

		body {
			color: #64748b;
			font-family: Arial, sans-serif;
			font-size: 9px;
			margin: 0;
			width: 100%;
		}

		.footer {
			border-top: 1px solid #e2e8f0;
			box-sizing: border-box;
			padding: 2mm 11mm 0;
			text-align: center;
			white-space: nowrap;
			width: 100%;
		}
	</style>
</head>
<body>
	<div class="footer">
		<span>Strana <span class="pageNumber"></span> z <span class="totalPages"></span></span>
	</div>
</body>
</html>
HTML;

	public function __construct(
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly string $baseUrl,
	)
	{
	}

	public function convertHtml(string $html): string
	{
		$response = $this->psr18ClientFactory
			->getClient([
				'connect_timeout' => 5,
				'timeout' => 120,
			])
			->sendRequest(
				$this->psr7RequestFactory->createMultipartPOSTRequest(
					rtrim($this->baseUrl, '/') . self::PDF_PATH,
					[
						[
							'name' => 'files',
							'contents' => $html,
							'filename' => 'index.html',
							'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
						],
						[
							'name' => 'files',
							'contents' => self::HEADER_HTML,
							'filename' => 'header.html',
							'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
						],
						[
							'name' => 'files',
							'contents' => self::FOOTER_HTML,
							'filename' => 'footer.html',
							'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
						],
						['name' => 'paperWidth', 'contents' => '8.27'],
						['name' => 'paperHeight', 'contents' => '11.7'],
						['name' => 'marginTop', 'contents' => '0.7'],
						['name' => 'marginBottom', 'contents' => '0.65'],
						['name' => 'marginLeft', 'contents' => '0.45'],
						['name' => 'marginRight', 'contents' => '0.45'],
						['name' => 'printBackground', 'contents' => 'true'],
						['name' => 'emulatedMediaType', 'contents' => 'screen'],
					],
					[
						'Accept' => 'application/pdf',
						'Gotenberg-Output-Filename' => 'portfolio-report',
					],
				),
			);

		if ($response->getStatusCode() !== 200) {
			$responseBody = trim((string) $response->getBody());

			throw new RuntimeException(sprintf(
				'Gotenberg PDF request failed with HTTP %d%s.',
				$response->getStatusCode(),
				$responseBody === '' ? '' : ': ' . $responseBody,
			));
		}

		$contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
		if ($contentType !== 'application/pdf') {
			throw new RuntimeException(sprintf(
				'Gotenberg PDF response has unexpected content type "%s".',
				$response->getHeaderLine('Content-Type'),
			));
		}

		return (string) $response->getBody();
	}

}
