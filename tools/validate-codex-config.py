#!/usr/bin/env python3

import re
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


def validate(root: Path) -> tuple[list[str], int]:
	errors = []
	read_toml(root / ".codex/config.toml", root, errors)
	agent_files = sorted((root / ".codex/agents").glob("*.toml"))
	if not agent_files:
		errors.append("No project agent definitions found in .codex/agents/.")

	skills = {path.parent.name for path in (root / ".agents/skills").glob("*/SKILL.md")}
	names = {}
	for path in agent_files:
		agent = read_toml(path, root, errors)
		relative_path = path.relative_to(root)
		for field in ("name", "description", "developer_instructions"):
			value = agent.get(field)
			if not isinstance(value, str) or not value.strip():
				errors.append(f"{relative_path}: {field} must be a non-empty string")

		name = agent.get("name")
		if isinstance(name, str) and name.strip():
			if name in names:
				errors.append(f"{relative_path}: duplicate agent name '{name}' (also in {names[name]})")
			names[name] = relative_path

		instructions = agent.get("developer_instructions")
		if not isinstance(instructions, str):
			continue

		# Role instructions use backtick-quoted names on lines mentioning skills.
		referenced_skills = set()
		for line in instructions.splitlines():
			if re.search(r"\bskills?\b", line, re.IGNORECASE):
				referenced_skills.update(re.findall(r"`([a-z0-9]+(?:-[a-z0-9]+)*)`", line))
		for skill in sorted(referenced_skills - skills):
			errors.append(f"{relative_path}: unknown project skill '{skill}'")

	return errors, len(agent_files)


def main() -> int:
	errors, agent_count = validate(Path(__file__).resolve().parent.parent)
	if errors:
		print("Codex configuration validation failed:", file=sys.stderr)
		for error in errors:
			print(f"- {error}", file=sys.stderr)
		return 1

	print(f"Validated Codex TOML configuration and {agent_count} project agents.")
	return 0


if __name__ == "__main__":
	sys.exit(main())
