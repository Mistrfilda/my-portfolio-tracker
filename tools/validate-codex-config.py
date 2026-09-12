#!/usr/bin/env python3

import sys
from pathlib import Path

try:
	import tomllib
except ModuleNotFoundError:
	sys.exit("Codex configuration validation requires Python 3.11+ (tomllib).")


def read_toml(path: Path, root: Path, errors: list[str]) -> dict:
	try:
		return tomllib.loads(path.read_text(encoding="utf-8"))
	except (OSError, UnicodeError, tomllib.TOMLDecodeError) as error:
		errors.append(f"{path.relative_to(root)}: {error}")
		return {}


def validate(root: Path) -> list[str]:
	errors = []
	read_toml(root / ".codex/config.toml", root, errors)
	return errors


def main() -> int:
	errors = validate(Path(__file__).resolve().parent.parent)
	if errors:
		print("Codex configuration validation failed:", file=sys.stderr)
		for error in errors:
			print(f"- {error}", file=sys.stderr)
		return 1

	print("Validated Codex TOML configuration.")
	return 0


if __name__ == "__main__":
	sys.exit(main())
