#!/bin/sh
set -e

# ==============================================================================
# Nightly Automated PostgreSQL Database Backup Script for USS Incident Reports
# ==============================================================================

BACKUP_DIR="/var/backups/uss_incidents"
RETENTION_DAYS=30
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FILENAME="uss_incidents_backup_${TIMESTAMP}.sql.gz"

mkdir -p "${BACKUP_DIR}"

echo "[$(date)] Starting PostgreSQL database backup..."

# Perform pg_dump compressed with gzip
pg_dump -U "${POSTGRES_USER:-postgres}" "${POSTGRES_DB:-uss_incidents}" | gzip > "${BACKUP_DIR}/${FILENAME}"

echo "[$(date)] Backup completed successfully: ${BACKUP_DIR}/${FILENAME}"

# Cleanup old backups exceeding retention period
echo "[$(date)] Removing backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -type f -name "uss_incidents_backup_*.sql.gz" -mtime +"${RETENTION_DAYS}" -exec rm {} \;

echo "[$(date)] Backup maintenance routine finished."
