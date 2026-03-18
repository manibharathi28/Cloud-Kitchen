#!/bin/bash

# Cloud Kitchen API Deployment Script
# This script automates the deployment process for production

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="cloud-kitchen-api"
BACKUP_DIR="/var/backups/$PROJECT_NAME"
LOG_FILE="/var/log/$PROJECT_NAME/deploy.log"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

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

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   error "This script should not be run as root"
   exit 1
fi

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR
mkdir -p $(dirname $LOG_FILE)

log "Starting deployment of $PROJECT_NAME"

# Check prerequisites
command -v docker >/dev/null 2>&1 || { error "Docker is not installed"; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { error "Docker Compose is not installed"; exit 1; }
command -v git >/dev/null 2>&1 || { error "Git is not installed"; exit 1; }

# Navigate to project directory
cd /var/www/$PROJECT_NAME || { error "Project directory not found"; exit 1; }

# Backup current database
log "Creating database backup"
docker-compose -f docker-compose.prod.yml exec -T mysql mysqldump -u root -p$DB_ROOT_PASSWORD cloud_kitchen_prod > $BACKUP_DIR/db_backup_$TIMESTAMP.sql

# Backup current code
log "Creating code backup"
tar -czf $BACKUP_DIR/code_backup_$TIMESTAMP.tar.gz --exclude='.git' --exclude='node_modules' --exclude='storage/logs/*' .

# Pull latest code
log "Pulling latest code from repository"
git fetch origin
git reset --hard origin/main

# Check if .env.production exists
if [ ! -f .env.production ]; then
    error ".env.production file not found"
    exit 1
fi

# Copy production environment file
log "Setting up environment"
cp .env.production .env

# Build and pull Docker images
log "Building Docker images"
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml pull

# Stop existing services
log "Stopping existing services"
docker-compose -f docker-compose.prod.yml down

# Start services
log "Starting services"
docker-compose -f docker-compose.prod.yml up -d

# Wait for services to be ready
log "Waiting for services to be ready"
sleep 30

# Run health checks
log "Performing health checks"

# Check if main service is running
if ! docker-compose -f docker-compose.prod.yml ps app | grep -q "Up"; then
    error "Main application service is not running"
    exit 1
fi

# Check database connection
if ! docker-compose -f docker-compose.prod.yml exec -T app php artisan migrate:status; then
    error "Database connection failed"
    exit 1
fi

# Run migrations
log "Running database migrations"
docker-compose -f docker-compose.prod.yml exec -T app php artisan migrate --force

# Clear and cache configurations
log "Optimizing application"
docker-compose -f docker-compose.prod.yml exec -T app php artisan config:clear
docker-compose -f docker-compose.prod.yml exec -T app php artisan cache:clear
docker-compose -f docker-compose.prod.yml exec -T app php artisan route:clear
docker-compose -f docker-compose.prod.yml exec -T app php artisan view:clear

docker-compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec -T app php artisan view:cache

# Restart queue workers
log "Restarting queue workers"
docker-compose -f docker-compose.prod.yml exec -T queue-worker php artisan queue:restart

# Run tests in production
log "Running production tests"
if ! docker-compose -f docker-compose.prod.yml exec -T app php artisan test --env=testing; then
    warning "Production tests failed, but deployment continues"
fi

# Cleanup old backups (keep last 5)
log "Cleaning up old backups"
cd $BACKUP_DIR
ls -t db_backup_*.sql | tail -n +6 | xargs -r rm
ls -t code_backup_*.tar.gz | tail -n +6 | xargs -r rm

# Check application health
log "Final health check"
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health || echo "000")

if [ "$HEALTH_CHECK" = "200" ]; then
    log "Deployment completed successfully!"
    log "Application is healthy and responding"
else
    error "Health check failed with status $HEALTH_CHECK"
    
    # Rollback if health check fails
    warning "Attempting rollback..."
    
    # Stop current services
    docker-compose -f docker-compose.prod.yml down
    
    # Restore code from backup
    tar -xzf $BACKUP_DIR/code_backup_$TIMESTAMP.tar.gz
    
    # Restart services
    docker-compose -f docker-compose.prod.yml up -d
    
    # Wait for services
    sleep 30
    
    # Restore database
    docker-compose -f docker-compose.prod.yml exec -T mysql mysql -u root -p$DB_ROOT_PASSWORD cloud_kitchen_prod < $BACKUP_DIR/db_backup_$TIMESTAMP.sql
    
    log "Rollback completed"
    exit 1
fi

# Send notification (if configured)
if command -v curl >/dev/null 2>&1 && [ -n "$SLACK_WEBHOOK" ]; then
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"✅ $PROJECT_NAME deployed successfully to production by $USER\"}" \
        $SLACK_WEBHOOK
fi

log "Deployment process completed successfully"
log "Backup files are located in $BACKUP_DIR"
log "Logs are available at $LOG_FILE"

exit 0
