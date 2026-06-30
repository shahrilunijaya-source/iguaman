<#
.SYNOPSIS
  Spin up an isolated git worktree for a parallel Claude session (collision-free).
  See docs/superpowers/plans/2026-06-30-concurrency-playbook.md.

.DESCRIPTION
  1 session = 1 worktree = 1 branch = 1 DB = 1 port. Creates a worktree under
  <main>/.worktrees/<branch> off the integration branch, gives it its OWN vendor via
  composer install (NO junctions — junctions to shared vendor are fragile: a recursive
  delete can chain through them and corrupt the main checkout), writes an isolated .env
  (own DB + APP_URL port), creates + migrates + seeds the DB.

  node_modules is intentionally skipped — public/build is committed, so the app runs
  on built assets. Run `npm install` in the worktree only if you need `npm run dev`.

.EXAMPLE
  scripts/new-worktree.ps1 -Branch batch-10-slot-calendar -Port 8779
#>
param(
  [Parameter(Mandatory = $true)][string]$Branch,
  [string]$DbName,
  [int]$Port = 8778,
  [string]$BaseBranch = "batch-7-rbac"
)

$ErrorActionPreference = "Stop"

# Anchor to the MAIN worktree regardless of current cwd (git-common-dir = <main>/.git).
$commonDir = (git rev-parse --path-format=absolute --git-common-dir).Trim()
$root = Split-Path $commonDir -Parent
Set-Location $root

if (-not $DbName) { $DbName = "iguaman_2in1_" + ($Branch -replace '[^a-zA-Z0-9]', '_') }
$wtPath = Join-Path $root ".worktrees/$Branch"

Write-Host "== worktree: .worktrees/$Branch  (branch $Branch off $BaseBranch, main=$root)" -ForegroundColor Cyan
git worktree add $wtPath -b $Branch $BaseBranch

# Isolated .env: copy main's, override DB + APP_URL port.
$envText = Get-Content (Join-Path $root ".env") -Raw
$envText = $envText -replace '(?m)^DB_DATABASE=.*$', "DB_DATABASE=$DbName"
$envText = $envText -replace '(?m)^APP_URL=.*$', "APP_URL=http://127.0.0.1:$Port"
Set-Content -Path (Join-Path $wtPath ".env") -Value $envText -NoNewline

Set-Location $wtPath

# Own vendor (no junctions). composer.lock is identical, so this is deterministic.
Write-Host "== composer install (own vendor)" -ForegroundColor Cyan
composer install --no-interaction --quiet

# Isolated DB (Laragon root, no password) via PDO.
Write-Host "== create database $DbName" -ForegroundColor Cyan
php -r "`$p=new PDO('mysql:host=127.0.0.1','root','');`$p->exec('CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');echo 'ok';"
Write-Host ""

php artisan migrate --force
php artisan db:seed --force

Set-Location $root  # leave the tool cwd at main so subsequent runs never nest

Write-Host ""
Write-Host "READY -> $wtPath" -ForegroundColor Green
Write-Host "  DB:    $DbName"
Write-Host "  serve: php artisan serve --port=$Port   (run from the worktree dir)"
Write-Host "  Open a NEW Claude session with this folder as cwd. Do NOT work here from the main session."
Write-Host ""
Write-Host "Cleanup when merged:  git worktree remove .worktrees/$Branch --force ; drop database $DbName"
