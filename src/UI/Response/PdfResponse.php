<?php

declare(strict_types = 1);

namespace App\UI\Response;

use Nette\Application\Response;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

final class PdfResponse implements Response
{

	public function __construct(
		private readonly string $content,
		private readonly string $name,
	)
	{
	}

	public function send(IRequest $httpRequest, IResponse $httpResponse): void
	{
		$httpResponse->setContentType('application/pdf');
		$httpResponse->setHeader('Cache-Control', 'private, no-store');
		$httpResponse->setHeader(
			'Content-Disposition',
			'attachment; filename="' . $this->name . '"; filename*=utf-8\'\'' . rawurlencode($this->name),
		);
		$httpResponse->setHeader('Content-Length', (string) strlen($this->content));

		echo $this->content;
	}

}
