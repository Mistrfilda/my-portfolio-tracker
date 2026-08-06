<?php

declare(strict_types = 1);

namespace App\Gotenberg;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use RuntimeException;

class GotenbergScreenshotService
{

	private const string SCREENSHOT_PATH = '/forms/chromium/screenshot/html';

	public function __construct(
		private readonly Psr18ClientFactory $psr18ClientFactory,
		private readonly Psr7RequestFactory $psr7RequestFactory,
		private readonly string $baseUrl,
	)
	{
	}

	public function captureHtml(string $html): string
	{
		$response = $this->psr18ClientFactory
			->getClient([
				'connect_timeout' => 5,
				'timeout' => 30,
			])
			->sendRequest(
				$this->psr7RequestFactory->createMultipartPOSTRequest(
					rtrim($this->baseUrl, '/') . self::SCREENSHOT_PATH,
					[
						[
							'name' => 'files',
							'contents' => $html,
							'filename' => 'index.html',
							'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
						],
						['name' => 'width', 'contents' => '1200'],
						['name' => 'height', 'contents' => '630'],
						['name' => 'clip', 'contents' => 'false'],
						['name' => 'format', 'contents' => 'png'],
					],
					[
						'Accept' => 'image/png',
						'Gotenberg-Output-Filename' => 'asset-trends',
					],
				),
			);

		if ($response->getStatusCode() !== 200) {
			$responseBody = trim((string) $response->getBody());

			throw new RuntimeException(sprintf(
				'Gotenberg screenshot request failed with HTTP %d%s.',
				$response->getStatusCode(),
				$responseBody === '' ? '' : ': ' . $responseBody,
			));
		}

		$contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
		if ($contentType !== 'image/png') {
			throw new RuntimeException(sprintf(
				'Gotenberg screenshot response has unexpected content type "%s".',
				$response->getHeaderLine('Content-Type'),
			));
		}

		return (string) $response->getBody();
	}

}
