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
use Slim\Routing\RouteContext;

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
			$path = $request->getUri()->getPath();
			$routeContext = RouteContext::fromRequest($request);
			$basePath = $routeContext->getBasePath();

			if ($basePath !== '' && str_starts_with($path, $basePath)) {
				$path = substr($path, strlen($basePath));
			}

			if ($path === '' || $path === '0') {
				$path = '/';
			}

			$operationAddress = new OperationAddress($path, strtolower($request->getMethod()));
			$responseValidator = $this->validatorBuilder->getResponseValidator();

			// Ensure response body is at the beginning
			$response->getBody()->rewind();

			try {
				$responseValidator->validate($operationAddress, $response);
			} finally {
				$response->getBody()->rewind();
			}
		} catch (NoResponseCode) {
			// Ignore if response code is not defined in spec
		} catch (ValidationFailed $e) {
			// In production, we might want to just log this, but for now we throw
			throw new RuntimeException(sprintf('Response validation failed: %s', $e->getMessage()), 0, $e);
		}

		return $response;
	}

}
