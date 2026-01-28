<?php

declare(strict_types = 1);

namespace App\Api;

use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;

class RequestValidationMiddleware implements MiddlewareInterface
{

	public function __construct(
		private string $openApiSpecPath,
		private ValidatorBuilder $validatorBuilder = new ValidatorBuilder(),
	)
	{
		$this->validatorBuilder = $this->validatorBuilder->fromYamlFile($this->openApiSpecPath);
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		try {
			$requestValidator = $this->validatorBuilder->getServerRequestValidator();
			$requestValidator->validate($request);
		} catch (ValidationFailed $e) {
			throw new HttpBadRequestException($request, $e->getMessage(), $e);
		}

		$response = $handler->handle($request);

		try {
			$operationAddress = new OperationAddress($request->getUri()->getPath(), strtolower($request->getMethod()));
			$responseValidator = $this->validatorBuilder->getResponseValidator();
			$responseValidator->validate($operationAddress, $response);
		} catch (NoResponseCode) {
			// Ignore if response code is not defined in spec
		} catch (ValidationFailed $e) {
			// In production, we might want to just log this, but for now we throw
			throw new RuntimeException(sprintf('Response validation failed: %s', $e->getMessage()), 0, $e);
		}

		return $response;
	}

}
