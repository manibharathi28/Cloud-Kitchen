#!/bin/bash

# Cloud Kitchen API Backup Script
# This script creates automated backups of the application and database

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="cloud-kitchen-api"
BACKUP_DIR="/var/backups/$PROJECT_NAME"
RETENTION_DAYS=30
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="/var/log/$PROJECT_NAME/backup.log"

# Functions
log() {
    echo -e "${GREEN}[$TIMESTAMP] $1${NC}"
    echo "[$TIMESTAMP] $1" >> $LOG_FILE
}

error() {
    echo -e "${RED}[$TIMESTAMP] ERROR: $1${NC}"
    echo "[$TIMESTAMP] ERROR: $1" >> $LOG_FILE
}

warning() {
    echo -e "${YELLOW}[$TIMESTAMP] WARNING: $1${NC}"
    echo "[$TIMESTAMP] WARNING: $1" >> $LOG_FILE
}

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR
mkdir -p $(dirname $LOG_FILE)

log "Starting backup process for $PROJECT_NAME"

# Check prerequisites
command -v docker >/dev/null 2>&1 || { error "Docker is not installed"; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { error "Docker Compose is not installed"; exit 1; }

# Navigate to project directory
cd /var/www/$PROJECT_NAME || { error "Project directory not found"; exit 1; }

# Create backup directory for this session
BACKUP_SESSION_DIR="$BACKUP_DIR/$TIMESTAMP"
mkdir -p $BACKUP_SESSION_DIR

# Database backup
log "Creating database backup"
DB_BACKUP_FILE="$BACKUP_SESSION_DIR/database.sql"

if docker-compose -f docker-compose.prod.yml exec -T mysql mysqldump -u root -p$DB_ROOT_PASSWORD --single-transaction --routines --triggers cloud_kitchen_prod > $DB_BACKUP_FILE; then
    log "Database backup completed"
    
    # Compress database backup
    gzip $DB_BACKUP_FILE
    log "Database backup compressed"
else
    error "Database backup failed"
    exit 1
fi

# Code backup
log "Creating code backup"
CODE_BACKUP_FILE="$BACKUP_SESSION_DIR/code.tar.gz"

# Exclude certain directories from code backup
tar -czf $CODE_BACKUP_FILE \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='bootstrap/cache/*' \
    .

log "Code backup completed"

# Storage backup
log "Creating storage backup"
STORAGE_BACKUP_FILE="$BACKUP_SESSION_DIR/storage.tar.gz"

tar -czf $STORAGE_BACKUP_FILE \
    storage/app/ \
    storage/framework/ \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*'

log "Storage backup completed"

# Environment files backup
log "Creating environment files backup"
cp .env.production $BACKUP_SESSION_DIR/.env.production.backup
cp docker-compose.prod.yml $BACKUP_SESSION_DIR/docker-compose.prod.yml.backup

log "Environment files backup completed"

# Create backup manifest
log "Creating backup manifest"
cat > $BACKUP_SESSION_DIR/manifest.txt << EOF
Backup Manifest
===============
Project: $PROJECT_NAME
Timestamp: $TIMESTAMP
Created by: $USER
Host: $(hostname)

Files:
- database.sql.gz: Database backup
- code.tar.gz: Application code
- storage.tar.gz: Storage files
- .env.production.backup: Environment configuration
- docker-compose.prod.yml.backup: Docker configuration

To restore:
1. Extract code.tar.gz to application directory
2. Import database.sql.gz to MySQL
3. Extract storage.tar.gz to storage directory
4. Copy .env.production.backup to .env.production
5. Restart services with docker-compose.prod.yml

EOF

# Verify backup integrity
log "Verifying backup integrity"
for file in "$BACKUP_SESSION_DIR"/*.tar.gz; do
    if ! tar -tzf "$file" > /dev/null; then
        error "Backup file $file is corrupted"
        exit 1
    fi
done

# Compress entire backup session
log "Compressing backup session"
cd $BACKUP_DIR
tar -czf "$TIMESTAMP.tar.gz" "$TIMESTAMP/"
rm -rf "$TIMESTAMP/"

# Calculate backup size
BACKUP_SIZE=$(du -h "$TIMESTAMP.tar.gz" | cut -f1)

# Cleanup old backups
log "Cleaning up backups older than $RETENTION_DAYS days"
find $BACKUP_DIR -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete

# List current backups
BACKUP_COUNT=$(find $BACKUP_DIR -name "*.tar.gz" | wc -l)
TOTAL_SIZE=$(du -sh $BACKUP_DIR | cut -f1)

log "Backup completed successfully!"
log "Backup size: $BACKUP_SIZE"
log "Total backups: $BACKUP_COUNT"
log "Total backup directory size: $TOTAL_SIZE"

# Send notification (if configured)
if command -v curl >/dev/null 2>&1 && [ -n "$SLACK_WEBHOOK" ]; then
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"📦 $PROJECT_NAME backup completed successfully ($BACKUP_SIZE)\"}" \
        $SLACK_WEBHOOK
fi

exit 0
