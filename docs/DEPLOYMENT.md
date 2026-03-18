# Deployment Guide

This guide provides comprehensive instructions for deploying the Cloud Kitchen Backend API to production environments.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Environment Setup](#environment-setup)
- [Docker Deployment](#docker-deployment)
- [Manual Deployment](#manual-deployment)
- [CI/CD Pipeline](#cicd-pipeline)
- [Monitoring & Health Checks](#monitoring--health-checks)
- [Backup & Recovery](#backup--recovery)
- [Troubleshooting](#troubleshooting)

## 🚀 Prerequisites

### System Requirements
- **Operating System**: Ubuntu 20.04+ or CentOS 8+
- **RAM**: Minimum 4GB, Recommended 8GB+
- **Storage**: Minimum 50GB SSD
- **CPU**: Minimum 2 cores, Recommended 4+ cores
- **Network**: Stable internet connection

### Software Requirements
- **Docker**: 20.10+
- **Docker Compose**: 2.0+
- **Git**: 2.25+
- **Nginx**: 1.18+ (if not using Docker)
- **PHP**: 8.2+ (if not using Docker)
- **MySQL**: 8.0+ (if not using Docker)
- **Redis**: 6.0+ (if not using Docker)

## 🔧 Environment Setup

### 1. Clone Repository
```bash
git clone https://github.com/your-org/cloud-kitchen-backend.git
cd cloud-kitchen-backend
```

### 2. Environment Configuration
```bash
# Copy production environment template
cp .env.production.example .env.production

# Edit environment variables
nano .env.production
```

### 3. Set Required Variables
```bash
# Generate application key
php artisan key:generate --env=production

# Set database credentials
DB_HOST=your_mysql_host
DB_DATABASE=cloud_kitchen_prod
DB_USERNAME=your_db_user
DB_PASSWORD=secure_password

# Set Redis configuration
REDIS_HOST=your_redis_host
REDIS_PASSWORD=redis_password

# Set mail configuration
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
```

## 🐳 Docker Deployment (Recommended)

### 1. Build and Start Services
```bash
# Start production services
docker-compose -f docker-compose.prod.yml up -d

# Build images (first time only)
docker-compose -f docker-compose.prod.yml build --no-cache
```

### 2. Run Database Migrations
```bash
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### 3. Optimize Application
```bash
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

### 4. Seed Database (Optional)
```bash
docker-compose -f docker-compose.prod.yml exec app php artisan db:seed --class=DatabaseSeeder --force
```

### 5. Verify Deployment
```bash
# Check service status
docker-compose -f docker-compose.prod.yml ps

# Check application health
curl http://localhost/api/health
```

## 🛠️ Manual Deployment

### 1. Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies (if needed)
npm install --production
npm run build
```

### 2. Configure Web Server

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/cloud-kitchen-backend/public;
    index index.php;

    SSL Configuration
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # API rate limiting
    location /api/ {
        limit_req zone=api burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### 3. Configure PHP-FPM
```ini
; /etc/php/8.2/fpm/php.ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 10000
```

### 4. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE cloud_kitchen_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cloud_kitchen'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON cloud_kitchen_prod.* TO 'cloud_kitchen'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php artisan migrate --force
```

### 5. Queue Worker Setup
```bash
# Create supervisor configuration
sudo nano /etc/supervisor/conf.d/cloud-kitchen-worker.conf
```

```ini
[program:cloud-kitchen-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cloud-kitchen-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cloud-kitchen-worker.log
stopwaitsecs=3600
```

```bash
# Start worker
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cloud-kitchen-worker:*
```

## 🔄 CI/CD Pipeline

### GitHub Actions Setup
1. **Repository Secrets**:
   - `DOCKER_USERNAME`: Docker Hub username
   - `DOCKER_PASSWORD`: Docker Hub password
   - `PROD_HOST`: Production server hostname
   - `PROD_USER`: SSH username
   - `PROD_SSH_KEY`: SSH private key
   - `SLACK_WEBHOOK`: Slack notification URL

2. **Automatic Deployment**:
   - Push to `main` branch triggers deployment
   - Tests run before deployment
   - Health checks verify deployment success
   - Rollback on failure

### Manual Deployment Script
```bash
# Make script executable
chmod +x scripts/deploy.sh

# Run deployment
./scripts/deploy.sh
```

## 📊 Monitoring & Health Checks

### Health Check Endpoints
- **Basic Health**: `GET /api/health`
- **Detailed Health**: `GET /api/health/detailed`
- **Ping**: `GET /api/ping`

### Monitoring Setup
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Set up log rotation
sudo nano /etc/logrotate.d/cloud-kitchen-api
```

```
/var/log/cloud-kitchen-api/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        docker-compose -f /var/www/cloud-kitchen-backend/docker-compose.prod.yml restart app
    endscript
}
```

### Performance Monitoring
```bash
# Monitor application performance
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-domain.com/api/health/detailed

# Check queue status
docker-compose -f docker-compose.prod.yml exec app php artisan queue:monitor

# Monitor logs
docker-compose -f docker-compose.prod.yml logs -f app
```

## 💾 Backup & Recovery

### Automated Backups
```bash
# Make backup script executable
chmod +x scripts/backup.sh

# Set up cron job for daily backups
crontab -e
```

```
# Daily backup at 2 AM
0 2 * * * /var/www/cloud-kitchen-backend/scripts/backup.sh
```

### Manual Backup
```bash
# Run manual backup
./scripts/backup.sh

# Backup database only
docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u root -p cloud_kitchen_prod > backup.sql
```

### Recovery Process
```bash
# Stop services
docker-compose -f docker-compose.prod.yml down

# Restore database
docker-compose -f docker-compose.prod.yml up -d mysql
docker-compose -f docker-compose.prod.yml exec mysql mysql -u root -p cloud_kitchen_prod < backup.sql

# Start all services
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Application Not Responding
```bash
# Check service status
docker-compose -f docker-compose.prod.yml ps

# Check logs
docker-compose -f docker-compose.prod.yml logs app

# Restart services
docker-compose -f docker-compose.prod.yml restart
```

#### 2. Database Connection Failed
```bash
# Check database status
docker-compose -f docker-compose.prod.yml exec mysql mysql -u root -p -e "SHOW PROCESSLIST;"

# Test connection from app
docker-compose -f docker-compose.prod.yml exec app php artisan tinker
>>> DB::connection()->getPdo();
```

#### 3. Queue Jobs Not Processing
```bash
# Check queue status
docker-compose -f docker-compose.prod.yml exec app php artisan queue:failed

# Restart queue worker
docker-compose -f docker-compose.prod.yml restart queue-worker

# Clear failed jobs
docker-compose -f docker-compose.prod.yml exec app php artisan queue:flush
```

#### 4. High Memory Usage
```bash
# Check memory usage
docker stats

# Clear caches
docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
docker-compose -f docker-compose.prod.yml exec app php artisan config:clear

# Restart services
docker-compose -f docker-compose.prod.yml restart
```

### Performance Optimization
```bash
# Optimize database
docker-compose -f docker-compose.prod.yml exec mysql mysql -u root -p -e "OPTIMIZE TABLE orders, menu_items, users;"

# Clear old logs
find storage/logs -name "*.log" -mtime +30 -delete

# Monitor slow queries
docker-compose -f docker-compose.prod.yml exec mysql mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

## 🔒 Security Considerations

### SSL/TLS Configuration
```bash
# Generate SSL certificates (Let's Encrypt)
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### Firewall Setup
```bash
# Configure UFW firewall
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

### Security Headers
The application automatically includes security headers:
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Strict-Transport-Security
- Content-Security-Policy

## 📞 Support

For deployment issues:
1. Check the troubleshooting section above
2. Review application logs: `docker-compose logs app`
3. Verify environment configuration
4. Check system resources: `docker stats`

For additional support, create an issue in the repository or contact the development team.

---

## 🚀 Quick Deployment Checklist

- [ ] Environment variables configured
- [ ] SSL certificates installed
- [ ] Database created and migrated
- [ ] Queue workers running
- [ ] Health checks passing
- [ ] Backup system configured
- [ ] Monitoring set up
- [ ] Security headers verified
- [ ] Rate limiting configured
- [ ] Log rotation configured

Once all items are checked, your Cloud Kitchen Backend API is production-ready!
