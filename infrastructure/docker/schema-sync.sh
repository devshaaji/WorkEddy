#!/bin/sh
set -eu

MAX_RETRIES="${STARTUP_MAX_RETRIES:-30}"
RETRY_SLEEP_SECONDS="${STARTUP_RETRY_SLEEP_SECONDS:-2}"
RECONCILE_INTERVAL_SECONDS="${MIGRATION_RECONCILE_INTERVAL_SECONDS:-15}"

log() {
  echo "[schema-sync] $1"
}

attempt=1
while [ "$attempt" -le "$MAX_RETRIES" ]; do
  if php /var/www/html/scripts/migrate.php migrate; then
    log "initial schema sync complete"
    break
  fi

  log "migrate attempt ${attempt}/${MAX_RETRIES} failed; retrying in ${RETRY_SLEEP_SECONDS}s"
  attempt=$((attempt + 1))
  sleep "$RETRY_SLEEP_SECONDS"
done

if [ "$attempt" -gt "$MAX_RETRIES" ]; then
  log "schema sync failed after ${MAX_RETRIES} attempts"
  exit 1
fi

while true; do
  sleep "$RECONCILE_INTERVAL_SECONDS"

  if php /var/www/html/scripts/migrate.php migrate; then
    log "schema reconciled"
    continue
  fi

  log "schema reconciliation failed; will retry in ${RECONCILE_INTERVAL_SECONDS}s"
done
