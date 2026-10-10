# GroPro Wholesale Order Management

This application is built for a wholesale business to replace handwritten order lists with a digital, tablet-first web interface. The system operates locally without needing an internet connection during normal operation.

## Architecture & Tech Stack

- **Backend**: Laravel 13 (PHP 8.3+)
- **Database**: PostgreSQL
- **Frontend**: Blade, HTML, CSS (Bootstrap 5), vanilla JS
- **Server**: Nginx & PHP-FPM
- **Sessions / cache / queues**: database drivers (no Redis)

---

## Arch Linux (dev) — PHP pgsql

```bash
sudo pacman -Syu php php-pgsql php-fpm postgresql nginx composer
# Enable extensions (uncomment if needed):
#   /etc/php/php.ini → extension=pgsql and extension=pdo_pgsql
sudo systemctl enable --now postgresql nginx
sudo -u postgres createuser -P gropro   # password: 1994
sudo -u postgres createdb -O gropro gropro
cd /path/to/gropro
cp .env.example .env && php artisan key:generate
# Set APP_URL to the LAN URL you will open, e.g. http://192.168.100.33:8000
# Keep SESSION_SECURE_COOKIE=false and SESSION_DOMAIN empty for http://IP access
composer install
php artisan migrate --seed
php artisan config:clear && php artisan cache:clear
php artisan serve --host=0.0.0.0 --port=8000
```

---

## Deployment Instructions (Ubuntu Server)

These instructions guide you through deploying the application on a fresh Ubuntu Server setup.

### 1. Install Dependencies

Update the system and install required packages:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install software-properties-common curl zip unzip git -y
sudo apt install nginx postgresql postgresql-contrib -y
```

### 2. Install PHP and Composer

Install PHP and necessary extensions:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.5-fpm php8.5-cli php8.5-pgsql php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip php8.5-intl -y
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. PostgreSQL Setup

Switch to the postgres user to create the database and user:

```bash
sudo -i -u postgres
psql
```

Inside the PostgreSQL prompt (`postgres=#`):

```sql
CREATE DATABASE gropro;
CREATE USER gropro WITH ENCRYPTED PASSWORD '1994';
GRANT ALL PRIVILEGES ON DATABASE gropro TO gropro;
-- Important: Transfer schema ownership
\c gropro
GRANT ALL ON SCHEMA public TO gropro;
\q
exit
```

### 4. Application Setup

Clone or move the project files to `/var/www/gropro`:

```bash
cd /var/www
sudo git clone <your-repo-url> gropro  # or copy the files directly
cd gropro
```

Set permissions:
```bash
sudo chown -R www-data:www-data /var/www/gropro
sudo chmod -R 775 storage bootstrap/cache
```

Install PHP dependencies:
```bash
sudo -u www-data composer install --optimize-autoloader --no-dev
```

Configure the environment:
```bash
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
```

Edit the `.env` file (`sudo nano .env`) and set database + LAN session settings:
```
# Use the exact URL phones open (include :8000 for artisan serve)
APP_URL=http://192.168.100.33:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gropro
DB_USERNAME=gropro
DB_PASSWORD=1994

SESSION_DRIVER=database
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

# Leave TRUSTED_PROXIES unset for artisan serve / direct PHP-FPM.
# Only set behind a real reverse proxy if needed.
```

`SESSION_SECURE_COOKIE=false` and an empty `SESSION_DOMAIN` are required so phone browsers keep the session on plain HTTP LAN IPs. Do not enable blanket trusted proxies on LAN — spoofed `X-Forwarded-Proto: https` was causing `https://` redirects and phone redirect loops.

Run database migrations and seed the initial admin account:
```bash
sudo -u www-data php artisan migrate --seed
```

*(This creates an initial admin account: `admin` / `password`)*

### 5. Nginx Configuration

Create a new Nginx configuration file:

```bash
sudo nano /etc/nginx/sites-available/gropro
```

Paste the following configuration (server IP `192.168.1.10`):

```nginx
server {
    listen 80;
    server_name 192.168.1.10;
    root /var/www/gropro/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/gropro /etc/nginx/sites-enabled/
sudo unlink /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

### 6. Ensuring Automatic Startup

Nginx, PHP-FPM, and PostgreSQL are configured to start automatically on Ubuntu by default. You can verify this with:

```bash
sudo systemctl enable nginx
sudo systemctl enable php8.5-fpm
sudo systemctl enable postgresql
```

### 7. Tablet Access

Workers can access the application by connecting to the local Wi-Fi network and entering the server's IP address in their browser:
`http://192.168.1.10`

---

## Daily Operations

### Importing the Daily Catalog
1. Login as Admin (`admin` / `password`).
2. Go to the "Catalog" tab.
3. Select the daily export file from Progilic (Excel TSV/CSV format).
4. Review the preview and valid/invalid counts.
5. Confirm the replacement. **Note: This clears all old orders to prepare for the new day.**

### Managing Workers
1. Go to the "Workers" tab in the Admin dashboard.
2. Add new workers, reset passwords, or disable accounts (mark them inactive so they can no longer log in).

### Database Backups
To create a simple daily backup, run this command on the server:

```bash
pg_dump -U postgres gropro > /path/to/backups/gropro_backup_$(date +%F).sql
```
To restore a backup:
```bash
psql -U postgres gropro < /path/to/backups/gropro_backup_YYYY-MM-DD.sql
```
