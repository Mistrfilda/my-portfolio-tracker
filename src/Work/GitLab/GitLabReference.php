<?php

declare(strict_types = 1);

namespace App\Work\GitLab;

use InvalidArgumentException;

readonly class GitLabReference
{

	/** @param 'issues'|'merge_requests' $resourceType */
	public function __construct(
		public string $projectPath,
		public string $resourceType,
		public int $iid,
		public int|null $noteId = null,
	)
	{
		if (
			preg_match('~^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+$~D', $projectPath) !== 1
			|| count(array_intersect(explode('/', $projectPath), ['.', '..'])) > 0
			|| !in_array($resourceType, ['issues', 'merge_requests'], true)
			|| $iid < 1 || ($noteId !== null && $noteId < 1)
		) {
			throw new InvalidArgumentException('Invalid GitLab reference.');
		}
	}

	public function getKey(): string
	{
		return $this->projectPath . '/-/' . $this->resourceType . '/' . $this->iid;
	}

	public function getUrl(): string
	{
		return 'https://gitlab.com/' . $this->getKey();
	}

	public function getApiPath(): string
	{
		return 'projects/' . rawurlencode($this->projectPath) . '/' . $this->resourceType . '/' . $this->iid;
	}

}
