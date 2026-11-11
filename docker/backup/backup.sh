#!/bin/bash
# Daily backup (spec §16): a dump of the database and an archive of the
# private files (permit PDFs, receipts, exports), kept BACKUP_RETENTION_DAYS
# (30) days. Runs in the "backup" service of compose.production.yaml.
#
#   backup.sh          one backup now
#   backup.sh --loop   one backup every day at BACKUP_AT (HH:MM, UTC; default 00:00 = 03:00 in Addis Ababa)
#
# Needs DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD; writes to /backups;
# reads the application's storage volume at /storage.
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/backups}"
RETENTION="${BACKUP_RETENTION_DAYS:-30}"

backup_once() {
    local stamp db files
    stamp="$(date -u +%Y%m%d-%H%M%S)"
    db="${BACKUP_DIR}/db-${stamp}.sql.gz"
    files="${BACKUP_DIR}/files-${stamp}.tar.gz"

    mkdir -p "${BACKUP_DIR}"

    # A consistent snapshot without locking the tables. DEFINER clauses are
    # dropped so that the dump restores under any account.
    MYSQL_PWD="${DB_PASSWORD}" mariadb-dump \
        --host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}" \
        --single-transaction --quick --routines --triggers --events \
        --default-character-set=utf8mb4 "${DB_DATABASE}" \
        | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' \
        | gzip -9 > "${db}.part"
    mv "${db}.part" "${db}"

    if [ -d /storage/app ]; then
        tar -czf "${files}.part" -C /storage app
        mv "${files}.part" "${files}"
    fi

    find "${BACKUP_DIR}" -maxdepth 1 \( -name 'db-*.sql.gz' -o -name 'files-*.tar.gz' \) -mtime +"${RETENTION}" -delete

    echo "Backup ${stamp} written to ${BACKUP_DIR}."
}

seconds_until_next_run() {
    local now target
    now="$(date -u +%s)"
    target="$(date -u -d "$(date -u +%Y-%m-%d) ${BACKUP_AT:-00:00}" +%s)"

    if [ "${target}" -le "${now}" ]; then
        target=$((target + 86400))
    fi

    echo $((target - now))
}

if [ "${1:-}" = "--loop" ]; then
    while true; do
        sleep "$(seconds_until_next_run)"
        backup_once || echo "Backup failed." >&2
    done
else
    backup_once
fi
