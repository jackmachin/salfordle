<#
.SYNOPSIS
    Deploy Salfordle to Hostinger: pull the latest main on the server, update it, and upload the frontend build.
.DESCRIPTION
    Shared hosting has no Node, so the frontend is built here and copied up. See DEPLOY.md for one-off setup.
.EXAMPLE
    ./deploy.ps1
    ./deploy.ps1 -Seed     # also reload streets from database/data/streets.json
#>
param(
    [string]$SshHost = 'limefinder-server',                       # alias from ~/.ssh/config (shared Hostinger account)
    [string]$RemotePath = 'domains/salfordle.co.uk/app',          # relative to the SSH user's home: works for ssh and scp
    [string]$Php = 'php',                                         # e.g. /opt/alt/php84/usr/bin/php if the default CLI is too old
    [switch]$Seed
)

$ErrorActionPreference = 'Stop'

function Invoke-Remote([string]$Command) {
    ssh $SshHost "cd $RemotePath && $Command"
    if ($LASTEXITCODE -ne 0) { throw "Remote command failed: $Command" }
}

if (git status --porcelain) {
    Write-Warning 'You have uncommitted changes. The server deploys GitHub main, but the frontend is built from this working copy.'
}

Write-Host '==> Building frontend' -ForegroundColor Cyan
npm ci
if ($LASTEXITCODE -ne 0) { throw 'npm ci failed' }
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }

Write-Host '==> Updating server code' -ForegroundColor Cyan
Invoke-Remote 'git pull --ff-only'
Invoke-Remote "$Php artisan optimize:clear"   # so a stale cached config can't drive the migration
Invoke-Remote 'composer install --no-dev --optimize-autoloader --no-interaction'
Invoke-Remote "$Php artisan migrate --force"
if ($Seed) { Invoke-Remote "$Php artisan db:seed --force" }

Write-Host '==> Uploading frontend build' -ForegroundColor Cyan
Invoke-Remote 'rm -rf public/build.new'
scp -r public/build "${SshHost}:$RemotePath/public/build.new"
if ($LASTEXITCODE -ne 0) { throw 'Upload failed' }
# Swap in one step, so visitors never get a half-uploaded build.
Invoke-Remote 'rm -rf public/build.old && (mv public/build public/build.old 2>/dev/null || true) && mv public/build.new public/build && rm -rf public/build.old'

Write-Host '==> Caching config, routes and views' -ForegroundColor Cyan
Invoke-Remote "$Php artisan optimize"

Write-Host 'Deployed.' -ForegroundColor Green
