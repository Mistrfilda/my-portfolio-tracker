<?php

declare(strict_types = 1);

namespace App\Work\Report;

use Nette\Utils\Strings;

class WorkReportSanitizer
{

	public function __construct(
		private readonly string $harvestAccessToken,
		private readonly string|null $gitlabAccessToken,
	)
	{
	}

	public function sanitize(string $text): string
	{
		foreach ([$this->harvestAccessToken, $this->gitlabAccessToken] as $token) {
			if ($token !== null && $token !== '') {
				$text = str_replace($token, '[REDACTED]', $text);
			}
		}

		$text = Strings::replace(
			$text,
			'~-----BEGIN ([A-Z ]*PRIVATE KEY)-----.*?-----END \1-----~s',
			'[REDACTED PRIVATE KEY]',
		);
		$text = Strings::replace(
			$text,
			'~\b(?:glpat-|gh[pousr]_|github_pat_|sk-(?:proj-)?)[A-Za-z0-9_-]{12,}~',
			'[REDACTED]',
		);
		$text = Strings::replace($text, '~\b(?:AKIA|ASIA)[A-Z0-9]{16}\b~', '[REDACTED]');
		$text = Strings::replace($text, '~\b([a-z][a-z0-9+.-]*://)[^\s/@]+:[^\s/@]+@~i', '$1[REDACTED]@');
		$text = Strings::replace($text, '~\b(Bearer|Basic)\s+[A-Za-z0-9+/_.=:-]+~i', '$1 [REDACTED]');
		$text = Strings::replace(
			$text,
			'~https?://share\.1password\.com/[^\s<>"\']+~i',
			'[REDACTED PASSWORD SHARE]',
		);
		$text = Strings::replace(
			$text,
			'~\b((?:heslo|heslem|password|passwd|token|secret|api[ _-]?key)\s+(?:je|is)\s+)'
			. '(?!\[REDACTED\])(?:"[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s&,;<>]+)~iu',
			'$1[REDACTED]',
		);
		$name = '[a-z0-9_-]*(?:heslo|heslem|password|passwd|pwd|secret|api[_-]?key|private[_-]?key|secret[_-]?access[_-]?key|token|authorization)';
		$key = '["\'`]?\b' . $name . '["\'`]?';
		$formattedKey = '["\'`]?' . $name . '["\'`]?';
		$separator = '(?:=>|[:=])';
		$label = '(?>(?<![*_])(?<markup>\*\*|__|\*|_)' . $formattedKey . '\h*'
			. '(?:\k<markup>\h*' . $separator . '|' . $separator . '\h*\k<markup>)'
			. '|(?<![*_])' . $key . '\h*' . $separator . ')';
		$value = <<<'REGEX'
(?:
	"(?:\\.|[^"\\\r\n])*"
	| '(?:\\.|[^'\\\r\n])*'
	| `[^`\r\n]*`
	| \*\*[^\r\n]*?\*\*
	| __[^\r\n]*?__
	| [^\s&,;<>]+
)
REGEX;
		return Strings::replace($text, '~(' . $label . '\s*)(?!\[REDACTED\])' . $value . '~iux', '$1[REDACTED]');
	}

	/**
	 * @template T of array<mixed>
	 * @param T $data
	 * @return T
	 */
	public function sanitizeData(array $data): array
	{
		array_walk_recursive($data, function (mixed &$value): void {
			if (is_string($value)) {
				$value = $this->sanitize($value);
			}
		});

		return $data;
	}

}
