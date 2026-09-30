# Deploying Salfordle (Hostinger shared hosting)

The setup: code is pulled from the public GitHub repo, the domain's web folder is a symlink to Laravel's `public/`, production runs MySQL, and the frontend is built locally and uploaded (shared hosting has no Node).

Salfordle shares a Hostinger account with other sites, reached over SSH as `limefinder-server` (defined in your `~/.ssh/config`: port 65002). Only ever touch `~/domains/salfordle.co.uk/` on it.

## One-off setup

### 1. Code (on the server)

```sh
ssh limefinder-server
cd ~/domains/salfordle.co.uk
git clone https://github.com/jackmachin/salfordle.git app
cd app
php -v                              # 8.4 on this server; Laravel 13 needs 8.3+
composer install --no-dev --optimize-autoloader --no-interaction
cp .env.example .env
php artisan key:generate
```

### 2. Database

Create a MySQL database and user in hPanel (**Databases → MySQL Databases**), then put them in `.env` (next step).

### 3. `.env` on the server

Edit `~/domains/salfordle.co.uk/app/.env` and set:

```ini
APP_NAME=Salfordle
APP_ENV=production
APP_DEBUG=false
APP_URL=https://salfordle.co.uk
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<from hPanel>
DB_USERNAME=<from hPanel>
DB_PASSWORD=<from hPanel>

SESSION_SECURE_COOKIE=true          # the player cookie is only sent over HTTPS

GOOGLE_MAPS_KEY=<key>
GOOGLE_MAPS_REFERER=https://salfordle.co.uk/   # only if the key is restricted by website, not IP
STREETLE_FIRST_PUZZLE_DATE=<launch day, YYYY-MM-DD>
STREETLE_REQUIRE_REVIEW=false       # true once answers are being reviewed
STREETLE_IMAGE_DAILY_CAP=330
```

### 4. Tables and street data

```sh
php artisan migrate --force
php artisan db:seed --force         # loads the 3,645 streets from database/data/streets.json
chmod -R ug+rwX storage bootstrap/cache
```

### 5. Point the domain at `public/`

```sh
cd ~/domains/salfordle.co.uk
mv public_html public_html.old      # keep Hostinger's placeholder until the site works
ln -s app/public public_html
```

In hPanel, make sure SSL is active for the domain, and that the website's PHP version is 8.3+ (**Advanced → PHP Configuration**).

### 6. First deploy of the frontend

Run the deploy script from your PC (below). It builds the frontend and uploads it.

## Every deploy

From the project folder on your PC:

```powershell
./deploy.ps1                 # pull, composer, migrate, upload the frontend, cache config/routes/views
./deploy.ps1 -Seed           # also reload streets, after re-exporting database/data/streets.json
```

Commit and push first. The server deploys what's on GitHub `main`, and the frontend is built from your working copy.

## After deploying

- Visit the site. You should see today's puzzle and the image.
- If the image fails, check `storage/logs/laravel.log` on the server for "Street View image fetch failed". A 403 means the key's restrictions are refusing the server (see `GOOGLE_MAPS_REFERER`).
- No cron job or queue worker is needed: each day's puzzle is created on its first visit.
