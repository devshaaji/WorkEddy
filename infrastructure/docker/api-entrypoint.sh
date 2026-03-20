#!/bin/sh
set -eu

MAX_RETRIES="${STARTUP_MAX_RETRIES:-30}"
SLEEP_SECONDS="${STARTUP_RETRY_SLEEP_SECONDS:-2}"

log() {
  echo "[api-entrypoint] $1"
}

ensure_dependencies() {
  if [ ! -f /var/www/html/vendor/autoload.php ]; then
    log "vendor/autoload.php not found; running composer install"
    composer install --prefer-dist --no-interaction --optimize-autoloader
  fi
}

ensure_storage_dirs() {
  mkdir -p \
    /storage/uploads \
    /storage/uploads/videos \
    /storage/uploads/pose \
    /storage/uploads/frames \
    /storage/uploads/processed \
    /storage/uploads/reports

  chmod 0775 \
    /storage/uploads \
    /storage/uploads/videos \
    /storage/uploads/pose \
    /storage/uploads/frames \
    /storage/uploads/processed \
    /storage/uploads/reports || true

  chown -R www-data:www-data /storage/uploads 2>/dev/null || true
}

wait_for_db_and_migrate() {
  attempt=1
  while [ "$attempt" -le "$MAX_RETRIES" ]; do
    if php /var/www/html/scripts/migrate.php migrate; then
      log "database migrations are up to date"
      return 0
    fi

    log "migrate attempt ${attempt}/${MAX_RETRIES} failed; retrying in ${SLEEP_SECONDS}s"
    attempt=$((attempt + 1))
    sleep "$SLEEP_SECONDS"
  done

  log "migration failed after ${MAX_RETRIES} attempts"
  return 1
}

ensure_dependencies
ensure_storage_dirs
wait_for_db_and_migrate

log "starting: $*"
exec "$@"
