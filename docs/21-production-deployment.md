# Production Deployment

This comprehensive guide covers deploying LeanPHP applications to production environments, including server configuration, security hardening, performance optimization, and deployment best practices.

## Table of Contents

- [Overview](#overview)
- [Environment Preparation](#environment-preparation)
- [Server Configuration](#server-configuration)
- [Application Optimization](#application-optimization)
- [Security Configuration](#security-configuration)
- [Database Setup](#database-setup)
- [File Permissions and Storage](#file-permissions-and-storage)
- [Caching and Performance](#caching-and-performance)
- [Environment Variables](#environment-variables)
- [Deployment Strategies](#deployment-strategies)
- [Container Deployment](#container-deployment)
- [Load Balancing](#load-balancing)
- [SSL/TLS Configuration](#ssltls-configuration)
- [Process Management](#process-management)
- [Backup and Recovery](#backup-and-recovery)
- [Troubleshooting](#troubleshooting)

## Overview

LeanPHP is designed for high-performance production deployments with minimal overhead. The framework follows the [Twelve-Factor App](https://12factor.net/) methodology, making it cloud-native and container-friendly.

### Key Production Components

- **Web Server**: Nginx or Apache as reverse proxy
- **PHP Runtime**: PHP 8.2+ with OpCache and APCu
- **Process Manager**: PHP-FPM for request handling
- **Database**: MySQL, PostgreSQL, or SQLite for production
- **Cache Layer**: APCu for rate limiting and OpCache for bytecode
- **Storage**: Persistent storage for logs and application data

## Environment Preparation

### PHP Installation and Configuration

#### Ubuntu/Debian Installation

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2 and required extensions
sudo apt install -y php8.2-fpm php8.2-cli php8.2-common \
    php8.2-mysql php8.2-pgsql php8.2-sqlite3 \
    php8.2-curl php8.2-json php8.2-mbstring \
    php8.2-opcache php8.2-apcu php8.2-sodium \
    composer nginx

# Enable and start services
sudo systemctl enable php8.2-fpm nginx
sudo systemctl start php8.2-fpm nginx
```

## Server Configuration

### Nginx Configuration
```nginx
# /etc/nginx/sites-available/leanphp-api

server {
    listen 80;
    server_name api.yourdomain.com;
    root /var/www/leanphp/public;
    index index.php;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Rate Limiting
    location /login {
        limit_req zone=login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP Processing
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        # Performance optimizations
        fastcgi_buffering on;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 16 16k;
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ ^/(config|src|vendor|tests|storage)/ {
        deny all;
    }

    location ~ \.(yml|yaml|ini|log|lock)$ {
        deny all;
    }

    # Health check endpoint (bypass rate limiting)
    location = /health {
        try_files $uri $uri/ /index.php?$query_string;
        access_log off;
    }

    # Static file optimization
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

## Application Optimization

### Route Caching

Enable route caching for production performance:

```bash
# Generate route cache
cd /var/www/leanphp
php bin/route_cache.php

# Verify cache file exists
ls -la storage/cache/routes.php
```

The route cache provides significant performance improvements:
- **85% faster route matching** (0.6ms vs 3.5ms)
- **Reduced memory usage** by eliminating route compilation overhead
- **Automatic activation** when `APP_ENV=production`

### Composer Optimization

```bash
# Install production dependencies only
composer install --no-dev --optimize-autoloader --no-scripts

# Generate optimized autoloader
composer dump-autoload --optimize --no-dev

# Clear development caches
rm -rf storage/cache/phpstan/
rm -rf storage/cache/phpunit/
```

### File Structure Optimization

```bash
# Set secure file permissions
find /var/www/leanphp -type f -exec chmod 644 {} \;
find /var/www/leanphp -type d -exec chmod 755 {} \;

# Make storage directories writable
chmod -R 775 /var/www/leanphp/storage/
chown -R www-data:www-data /var/www/leanphp/storage/

# Secure sensitive files
chmod 600 /var/www/leanphp/.env
chown www-data:www-data /var/www/leanphp/.env
```

## Security Configuration

### Environment Variables Security

#### Production .env Configuration

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
APP_TIMEZONE=UTC

# Logging (use syslog or centralized logging in production)
LOG_PATH=/var/log/leanphp/app.log

# Database (use strong credentials)
DB_DSN=mysql:host=localhost;dbname=leanphp_prod;charset=utf8mb4
DB_USER=leanphp_user
DB_PASSWORD=very_strong_password_here_32_chars_min
DB_ATTR_PERSISTENT=true

# JWT Authentication (rotate regularly)
AUTH_JWT_CURRENT_KID=prod_2024_09
AUTH_JWT_KEYS=prod_2024_09:securely_generated_base64url_key_here
AUTH_TOKEN_TTL=900

# CORS (restrictive for production)
CORS_ALLOW_ORIGINS=https://app.yourdomain.com,https://admin.yourdomain.com
CORS_ALLOW_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOW_HEADERS=Authorization,Content-Type,X-Requested-With
CORS_MAX_AGE=600
CORS_ALLOW_CREDENTIALS=false

# Rate Limiting (use APCu for performance)
RATE_LIMIT_STORE=apcu
RATE_LIMIT_DEFAULT=60
RATE_LIMIT_WINDOW=60
```

#### Secure Key Generation

```bash
# Generate secure JWT keys
openssl rand -base64 32 | tr '+/' '-_' | tr -d '='

# Generate strong database password
openssl rand -base64 32

# Example key rotation script
#!/bin/bash
NEW_KEY_ID="prod_$(date +%Y_%m)"
NEW_SECRET=$(openssl rand -base64 32 | tr '+/' '-_' | tr -d '=')
echo "AUTH_JWT_CURRENT_KID=${NEW_KEY_ID}"
echo "AUTH_JWT_KEYS=${NEW_KEY_ID}:${NEW_SECRET}"
```

### File Permissions and Security

```bash
# Create secure directory structure
sudo mkdir -p /var/www/leanphp
sudo mkdir -p /var/log/leanphp
sudo mkdir -p /etc/leanphp

# Set ownership
sudo chown -R www-data:www-data /var/www/leanphp
sudo chown -R www-data:www-data /var/log/leanphp

# Set base permissions
sudo find /var/www/leanphp -type f -exec chmod 644 {} \;
sudo find /var/www/leanphp -type d -exec chmod 755 {} \;

# Secure storage directories
sudo chmod -R 775 /var/www/leanphp/storage
sudo chmod -R 775 /var/log/leanphp

# Secure configuration
sudo chmod 600 /var/www/leanphp/.env

# Remove write permissions from public directory
sudo chmod -R 755 /var/www/leanphp/public
```

## Database Setup

### Database Seeding

```bash
# Seed production data
php scripts/seed.php --env=production


## File Permissions and Storage

### Storage Directory Structure

```bash
# Create required storage directories
mkdir -p /var/www/leanphp/storage/{logs,cache,ratelimit}
mkdir -p /var/log/leanphp

# Set appropriate permissions
chown -R www-data:www-data /var/www/leanphp/storage
chown -R www-data:www-data /var/log/leanphp
chmod -R 775 /var/www/leanphp/storage
chmod -R 775 /var/log/leanphp

# Create symbolic link for centralized logging
ln -sf /var/log/leanphp/app.log /var/www/leanphp/storage/logs/app.log
```
