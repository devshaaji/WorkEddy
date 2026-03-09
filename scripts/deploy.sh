#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-up}"

case "$MODE" in
	up)
		docker compose up -d --build
		;;

	seed)
		docker compose run --rm --profile ops seed
		;;

	up-and-seed)
		docker compose up -d --build
		docker compose run --rm --profile ops seed
		;;

	*)
		echo "Usage: $0 [up|seed|up-and-seed]"
		exit 1
		;;
esac
