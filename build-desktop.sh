#!/usr/bin/env bash
set -euo pipefail

if [[ "${1:-}" == "--help" ]]; then
	printf '%s\n' \
		'Usage: ./build-desktop.sh' \
		'Build a local ad-hoc-signed macOS arm64 application and ZIP.' \
		'Runs npm ci, checks, compilation, and packaging.' \
		'Output: clients/desktop/artifacts/'
	exit 0
fi

if [[ $# -ne 0 ]]; then
	printf '%s\n' 'Unknown argument. Use --help for usage.' >&2
	exit 1
fi

if [[ "$(uname -s)" != "Darwin" ]]; then
	printf '%s\n' 'The desktop build requires macOS.' >&2
	exit 1
fi

DESKTOP_REPO_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$DESKTOP_REPO_DIR/clients/desktop"

npm ci
npm run check
npm run build
npm run package:mac

printf '%s\n' "Desktop build ready: $DESKTOP_REPO_DIR/clients/desktop/artifacts/"
