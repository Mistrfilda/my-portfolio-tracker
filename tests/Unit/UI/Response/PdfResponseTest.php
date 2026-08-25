<?php

declare(strict_types = 1);

namespace App\Test\Unit\UI\Response;

use App\UI\Response\PdfResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use PHPUnit\Framework\TestCase;

class PdfResponseTest extends TestCase
{

	public function testSendOutputsPdfAsPrivateAttachment(): void
	{
		$headers = [];
		$httpResponse = $this->createMock(IResponse::class);
		$httpResponse
			->expects($this->once())
			->method('setContentType')
			->with('application/pdf')
			->willReturnSelf();
		$httpResponse
			->expects($this->exactly(3))
			->method('setHeader')
			->willReturnCallback(
				static function (string $name, string $value) use (&$headers, $httpResponse): IResponse {
					$headers[$name] = $value;

					return $httpResponse;
				},
			);

		ob_start();
		try {
			(new PdfResponse('pdf-content', 'analysis.pdf'))->send(
				$this->createStub(IRequest::class),
				$httpResponse,
			);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		self::assertSame('pdf-content', $output);
		self::assertSame('private, no-store', $headers['Cache-Control']);
		self::assertSame(
			'attachment; filename="analysis.pdf"; filename*=utf-8\'\'analysis.pdf',
			$headers['Content-Disposition'],
		);
		self::assertSame('11', $headers['Content-Length']);
	}

}
