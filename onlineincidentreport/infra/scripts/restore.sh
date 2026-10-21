#!/bin/sh
set -e

# ==============================================================================
# Database Restore Script for USS Incident Reports
# Usage: ./restore.sh /path/to/uss_incidents_backup_YYYYMMDD_HHMMSS.sql.gz
# ==============================================================================

if [ -z "$1" ]; then
    echo "Usage: $0 <path_to_backup_file.sql.gz>"
    exit 1
fi

BACKUP_FILE="$1"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Error: Backup file '$BACKUP_FILE' not found!"
    exit 1
fi

echo "WARNING: Restoring will overwrite existing data in '${POSTGRES_DB:-uss_incidents}'!"
read -p "Are you sure you want to proceed? (y/N): " CONFIRM

if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
    echo "Restore cancelled."
    exit 0
fi

echo "[$(date)] Starting restoration from ${BACKUP_FILE}..."

gunzip -c "$BACKUP_FILE" | psql -U "${POSTGRES_USER:-postgres}" -d "${POSTGRES_DB:-uss_incidents}"

echo "[$(date)] Restoration completed successfully!"
