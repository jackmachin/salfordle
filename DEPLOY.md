# Deploying Salfordle (Hostinger shared hosting)

The setup: code is pulled from GitHub over SSH, the domain's web folder is a symlink to Laravel's `public/`, production runs MySQL, and the frontend is built locally and uploaded (shared hosting has no Node).

Hostinger's SSH usually listens on port **65002**. The details (user, host, port) are in hPanel under **Advanced → SSH Access**. Paths below assume Hostinger's usual layout, `~/domains/salfordle.co.uk/`. Adjust if yours differs.

## One-off setup

### 1. SSH alias (on your PC)

Add to `C:\Users\<you>\.ssh\config`, so every command below can just say `salfordle`:

```
Host salfordle
    HostName <server IP or hostname from hPanel>
    Port 65002
    User <ssh user from hPanel>
```

### 2. Deploy key (on the server)

```sh
ssh salfordle
ssh-keygen -t ed25519 -C "salfordle deploy" -f ~/.ssh/salfordle_deploy -N ""
cat ~/.ssh/salfordle_deploy.pub     # add this on GitHub: repo → Settings → Deploy keys (read-only)
cat >> ~/.ssh/config <<'EOF'
Host github-salfordle
    HostName github.com
    IdentityFile ~/.ssh/salfordle_deploy
    IdentitiesOnly yes
EOF
```

### 3. Code (on the server)

```sh
cd ~/domains/salfordle.co.uk
git clone git@github-salfordle:<github user>/salfordle.git app
cd app
php -v                              # must be 8.3+; if not, use Hostinger's full path, e.g. /opt/alt/php84/usr/bin/php
composer install --no-dev --optimize-autoloader --no-interaction
cp .env.example .env
php artisan key:generate
```

### 4. Database

Create a MySQL database and user in hPanel (**Databases → MySQL Databases**), then put them in `.env` (next step).

### 5. `.env` on the server

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

### 6. Tables and street data

```sh
php artisan migrate --force
php artisan db:seed --force         # loads the 3,645 streets from database/data/streets.json
chmod -R ug+rwX storage bootstrap/cache
```

### 7. Point the domain at `public/`

```sh
cd ~/domains/salfordle.co.uk
mv public_html public_html.old      # keep Hostinger's placeholder until the site works
ln -s app/public public_html
```

In hPanel, make sure SSL is active for the domain, and that the website's PHP version is 8.3+ (**Advanced → PHP Configuration**).

### 8. First deploy of the frontend

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
