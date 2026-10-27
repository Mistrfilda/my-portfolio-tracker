<?php

declare(strict_types = 1);

namespace App\Work\GitLab;

class GitLabReferenceExtractor
{

	private const string URL_PATTERN = '~https://gitlab\.com/[^\s<>"\']+~i';

	/** @return list<GitLabReference> */
	public function extract(string $text): array
	{
		preg_match_all(self::URL_PATTERN, $text, $urls);
		$references = [];
		foreach ($urls[0] as $url) {
			$parts = parse_url(rtrim($url, ').,;:!?]}'));
			if (
				$parts === false || strtolower($parts['host'] ?? '') !== 'gitlab.com'
				|| isset($parts['user']) || isset($parts['port'])
				|| preg_match(
					'~^([A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+)/-/(issues|merge_requests|work_items)/([1-9][0-9]*)(?:/[^?#]*)?$~D',
					ltrim($parts['path'] ?? '', '/'),
					$matches,
				) !== 1
				|| count(array_intersect(explode('/', $matches[1]), ['.', '..'])) > 0
			) {
				continue;
			}

			$noteId = preg_match(
				'~^note_([1-9][0-9]*)$~D',
				$parts['fragment'] ?? '',
				$note,
			) === 1
				? (int) $note[1]
				: null;
			// Project work-item UI URLs resolve through the Issues API, including their notes.
			$resourceType = $matches[2] === 'merge_requests' ? 'merge_requests' : 'issues';
			$reference = new GitLabReference($matches[1], $resourceType, (int) $matches[3], $noteId);
			$references[$reference->getKey() . '#' . $noteId] = $reference;
		}

		return array_values($references);
	}

	public function countUnsupportedUrls(string $text): int
	{
		preg_match_all(self::URL_PATTERN, $text, $urls);
		return count(array_filter(
			array_unique($urls[0]),
			fn (string $url): bool => $this->extract($url) === [],
		));
	}

}
