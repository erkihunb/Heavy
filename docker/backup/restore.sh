#!/bin/bash
# Restore a backup made by backup.sh (spec §16). Stop the app, worker and
# scheduler first, so nothing writes while the data is replaced:
#
#   restore.sh /backups/db-YYYYMMDD-HHMMSS.sql.gz [/backups/files-YYYYMMDD-HHMMSS.tar.gz]
#
# The database dump replaces every table it contains; the files archive is
# unpacked over /storage/app. Needs DB_HOST, DB_DATABASE, DB_USERNAME,
# DB_PASSWORD.
set -euo pipefail

if [ $# -lt 1 ] || [ ! -f "$1" ]; then
    echo "Usage: restore.sh <db-….sql.gz> [files-….tar.gz]" >&2
    exit 1
fi

echo "Restoring the database ${DB_DATABASE} from $1 …"
gunzip -c "$1" | MYSQL_PWD="${DB_PASSWORD}" mariadb \
    --host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}" \
    --default-character-set=utf8mb4 "${DB_DATABASE}"

if [ -n "${2:-}" ]; then
    if [ ! -f "$2" ]; then
        echo "No such file: $2" >&2
        exit 1
    fi

    echo "Restoring the files from $2 …"
    tar -xzf "$2" -C /storage
fi

echo "Restore finished. Start the application again and check /up."
