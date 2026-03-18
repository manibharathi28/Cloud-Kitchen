#!/bin/bash

# Production Environment Setup Script
# This script sets up a production server for Cloud Kitchen Backend API

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="cloud-kitchen-api"
PROJECT_DIR="/var/www/$PROJECT_NAME"
LOG_FILE="/var/log/$PROJECT_NAME/setup.log"
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

info() {
    echo -e "${BLUE}[$TIMESTAMP] INFO: $1${NC}"
    echo "[$TIMESTAMP] INFO: $1" >> $LOG_FILE
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   error "This script should not be run as root. Run as a sudo user."
   exit 1
fi

# Check if user has sudo privileges
if ! sudo -n true 2>/dev/null; then
    error "This script requires sudo privileges"
    exit 1
fi

# Create log directory
sudo mkdir -p $(dirname $LOG_FILE)
sudo touch $LOG_FILE
sudo chown $USER:$USER $LOG_FILE

log "Starting production environment setup for $PROJECT_NAME"

# System update
log "Updating system packages"
sudo apt update && sudo apt upgrade -y

# Install required packages
log "Installing required packages"
sudo apt install -y \
    curl \
    wget \
    git \
    unzip \
    htop \
    iotop \
    nethogs \
    nginx \
    certbot \
    python3-certbot-nginx \
    supervisor \
    ufw

# Install Docker
log "Installing Docker"
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
    sudo usermod -aG docker $USER
    rm get-docker.sh
    log "Docker installed successfully"
else
    warning "Docker is already installed"
fi

# Install Docker Compose
log "Installing Docker Compose"
if ! command -v docker-compose >/dev/null 2>&1; then
    sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
    log "Docker Compose installed successfully"
else
    warning "Docker Compose is already installed"
fi

# Create project directory
log "Creating project directory"
sudo mkdir -p $PROJECT_DIR
sudo chown $USER:$USER $PROJECT_DIR

# Clone repository (if not already cloned)
if [ ! -d "$PROJECT_DIR/.git" ]; then
    log "Cloning repository"
    read -p "Enter the repository URL: " REPO_URL
    git clone $REPO_URL $PROJECT_DIR
    cd $PROJECT_DIR
else
    log "Repository already exists, pulling latest changes"
    cd $PROJECT_DIR
    git pull origin main
fi

# Setup environment file
log "Setting up environment configuration"
if [ ! -f "$PROJECT_DIR/.env.production" ]; then
    cp .env.production.example .env.production
    
    # Generate application key
    php artisan key:generate --env=production
    
    warning "Please edit .env.production with your production settings"
    read -p "Press Enter to continue after editing .env.production..."
else
    info "Environment file already exists"
fi

# Setup SSL with Let's Encrypt
log "Setting up SSL certificate"
read -p "Enter your domain name: " DOMAIN_NAME

if [ ! -z "$DOMAIN_NAME" ]; then
    sudo certbot --nginx -d $DOMAIN_NAME --non-interactive --agree-tos --email admin@$DOMAIN_NAME || {
        warning "SSL setup failed, you may need to configure it manually"
    }
else
    warning "No domain provided, skipping SSL setup"
fi

# Configure firewall
log "Configuring firewall"
sudo ufw --force reset
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw --force enable

# Setup log rotation
log "Setting up log rotation"
sudo tee /etc/logrotate.d/$PROJECT_NAME > /dev/null << EOF
$PROJECT_DIR/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        cd $PROJECT_DIR && docker-compose -f docker-compose.prod.yml restart app
    endscript
}
EOF

# Setup backup cron job
log "Setting up automated backups"
(crontab -l 2>/dev/null; echo "0 2 * * * $PROJECT_DIR/scripts/backup.sh") | crontab -

# Create backup directory
sudo mkdir -p /var/backups/$PROJECT_NAME
sudo chown $USER:$USER /var/backups/$PROJECT_NAME

# Setup monitoring
log "Setting up basic monitoring"
sudo tee /etc/cron.d/$PROJECT_NAME-monitoring > /dev/null << EOF
# Monitor disk space every hour
0 * * * * $USER df -h | grep -E '/$|/var|/tmp' | mail -s "Disk Usage Report for $PROJECT_NAME" admin@$DOMAIN_NAME

# Monitor application health every 5 minutes
*/5 * * * * $USER curl -f http://localhost/api/health || mail -s "Application Health Check Failed for $PROJECT_NAME" admin@$DOMAIN_NAME
EOF

# Build and start Docker containers
log "Building and starting Docker containers"
cd $PROJECT_DIR
docker-compose -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.prod.yml up -d

# Wait for services to be ready
log "Waiting for services to start"
sleep 30

# Run database migrations
log "Running database migrations"
docker-compose -f docker-compose.prod.yml exec -T app php artisan migrate --force

# Optimize application
log "Optimizing application for production"
docker-compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec -T app php artisan view:cache

# Seed database (optional)
read -p "Do you want to seed the database with sample data? (y/n): " SEED_DB
if [[ $SEED_DB =~ ^[Yy]$ ]]; then
    log "Seeding database"
    docker-compose -f docker-compose.prod.yml exec -T app php artisan db:seed --class=DatabaseSeeder --force
fi

# Health check
log "Performing health check"
if curl -f http://localhost/api/health > /dev/null 2>&1; then
    log "Application is healthy and responding"
else
    error "Health check failed"
    exit 1
fi

# Create startup script
log "Creating startup script"
sudo tee /usr/local/bin/$PROJECT_NAME-start > /dev/null << EOF
#!/bin/bash
cd $PROJECT_DIR
docker-compose -f docker-compose.prod.yml up -d
echo "$PROJECT_NAME services started"
EOF

sudo chmod +x /usr/local/bin/$PROJECT_NAME-start

# Create shutdown script
log "Creating shutdown script"
sudo tee /usr/local/bin/$PROJECT_NAME-stop > /dev/null << EOF
#!/bin/bash
cd $PROJECT_DIR
docker-compose -f docker-compose.prod.yml down
echo "$PROJECT_NAME services stopped"
EOF

sudo chmod +x /usr/local/bin/$PROJECT_NAME-stop

# Create status script
log "Creating status script"
sudo tee /usr/local/bin/$PROJECT_NAME-status > /dev/null << EOF
#!/bin/bash
echo "=== $PROJECT_NAME Status ==="
cd $PROJECT_DIR
docker-compose -f docker-compose.prod.yml ps
echo ""
echo "=== Health Check ==="
curl -s http://localhost/api/health | jq '.' || echo "Health check failed"
echo ""
echo "=== Disk Usage ==="
df -h | grep -E '/$|/var|/tmp'
echo ""
echo "=== Memory Usage ==="
free -h
echo ""
echo "=== System Load ==="
uptime
EOF

sudo chmod +x /usr/local/bin/$PROJECT_NAME-status

# Setup log monitoring
log "Setting up log monitoring"
sudo tee /etc/rsyslog.d/$PROJECT_NAME.conf > /dev/null << EOF
# Forward application logs to centralized log file
:programname, isequal, "$PROJECT_NAME-app" /var/log/$PROJECT_NAME/application.log
& stop
EOF

sudo systemctl restart rsyslog

# Create systemd service for automatic startup
log "Creating systemd service"
sudo tee /etc/systemd/system/$PROJECT_NAME.service > /dev/null << EOF
[Unit]
Description=$PROJECT_NAME Docker Compose Application
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/local/bin/docker-compose -f docker-compose.prod.yml up -d
ExecStop=/usr/local/bin/docker-compose -f docker-compose.prod.yml down
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable $PROJECT_NAME.service

# Final setup summary
log "Production setup completed successfully!"
echo ""
echo -e "${GREEN}=== Setup Summary ===${NC}"
echo -e "${BLUE}Project Directory:${NC} $PROJECT_DIR"
echo -e "${BLUE}Application URL:${NC} http://localhost"
if [ ! -z "$DOMAIN_NAME" ]; then
    echo -e "${BLUE}Domain:${NC} https://$DOMAIN_NAME"
fi
echo -e "${BLUE}Health Check:${NC} http://localhost/api/health"
echo ""
echo -e "${GREEN}=== Useful Commands ===${NC}"
echo -e "${BLUE}Start services:${NC} sudo systemctl start $PROJECT_NAME"
echo -e "${BLUE}Stop services:${NC} sudo systemctl stop $PROJECT_NAME"
echo -e "${BLUE}Check status:${NC} $PROJECT_NAME-status"
echo -e "${BLUE}View logs:${NC} cd $PROJECT_DIR && docker-compose -f docker-compose.prod.yml logs -f"
echo -e "${BLUE}Deploy updates:${NC} cd $PROJECT_DIR && ./scripts/deploy.sh"
echo -e "${BLUE}Manual backup:${NC} cd $PROJECT_DIR && ./scripts/backup.sh"
echo ""
echo -e "${GREEN}=== Next Steps ===${NC}"
echo "1. Configure your domain DNS to point to this server"
echo "2. Update your .env.production with mail credentials"
echo "3. Set up monitoring and alerting"
echo "4. Configure backup storage (S3/external)"
echo "5. Test all API endpoints"
echo "6. Set up CI/CD pipeline"
echo ""
echo -e "${YELLOW}Important:${NC} Please save your credentials and configuration securely!"
echo -e "${YELLOW}Logs:${NC} $LOG_FILE"

exit 0
