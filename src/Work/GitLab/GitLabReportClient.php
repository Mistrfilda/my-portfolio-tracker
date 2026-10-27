<?php

declare(strict_types = 1);

namespace App\Work\GitLab;

use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\Utils\TypeValidator;
use App\Work\Report\WorkReportException;
use InvalidArgumentException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * @phpstan-type GitLabItemData array{title: string, state: string, description: string|null, updatedAt: string}
 * @phpstan-type GitLabNoteData array{id: int, body: string, author: string, createdAt: string, updatedAt: string}
 */
class GitLabReportClient
{

	public function __construct(
		private readonly string|null $gitlabAccessToken,
		private readonly Psr18ClientFactory $clientFactory,
		private readonly Psr7RequestFactory $requestFactory,
	)
	{
	}

	public function assertConfigured(): void
	{
		if ($this->gitlabAccessToken === null || trim($this->gitlabAccessToken) === '') {
			throw new WorkReportException(
				'Configure parameters.workReport.gitlabAccessToken before generating a work report.',
			);
		}
	}

	/** @return GitLabItemData */
	public function getItem(GitLabReference $reference): array
	{
		$data = $this->get($reference->getApiPath());
		try {
			return [
				'title' => TypeValidator::validateString($data['title'] ?? null),
				'state' => TypeValidator::validateString($data['state'] ?? null),
				'description' => TypeValidator::validateNullableString($data['description'] ?? null),
				'updatedAt' => TypeValidator::validateString($data['updated_at'] ?? null),
			];
		} catch (InvalidArgumentException) {
			throw new WorkReportException('GitLab returned an invalid item.');
		}
	}

	/** @return GitLabNoteData */
	public function getNote(GitLabReference $reference): array
	{
		if ($reference->noteId === null) {
			throw new InvalidArgumentException('A GitLab note ID is required.');
		}

		$data = $this->get($reference->getApiPath() . '/notes/' . $reference->noteId);
		try {
			$author = TypeValidator::validateArray($data['author'] ?? null);
			return [
				'id' => TypeValidator::validateInt($data['id'] ?? null),
				'body' => TypeValidator::validateString($data['body'] ?? null),
				'author' => TypeValidator::validateString($author['name'] ?? null),
				'createdAt' => TypeValidator::validateString($data['created_at'] ?? null),
				'updatedAt' => TypeValidator::validateString($data['updated_at'] ?? null),
			];
		} catch (InvalidArgumentException) {
			throw new WorkReportException('GitLab returned an invalid note.');
		}
	}

	/** @return array<mixed> */
	private function get(string $path): array
	{
		$this->assertConfigured();
		try {
			$response = $this->clientFactory->getClient([
				'connect_timeout' => 5,
				'timeout' => 30,
				'allow_redirects' => false,
				'http_errors' => false,
			])->sendRequest($this->requestFactory->createGETRequest('https://gitlab.com/api/v4/' . $path, [
				'PRIVATE-TOKEN' => $this->gitlabAccessToken ?? '',
				'Accept' => 'application/json',
			]));
		} catch (ClientExceptionInterface) {
			// Do not retain transport exceptions containing authenticated requests.
			throw new WorkReportException('GitLab request failed.');
		}

		if ($response->getStatusCode() !== 200) {
			throw new WorkReportException(sprintf('GitLab returned HTTP %d.', $response->getStatusCode()));
		}

		try {
			return TypeValidator::validateArray(Json::decode((string) $response->getBody(), true));
		} catch (JsonException | InvalidArgumentException) {
			throw new WorkReportException('GitLab returned an invalid JSON response.');
		}
	}

}
