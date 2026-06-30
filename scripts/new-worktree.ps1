<#
.SYNOPSIS
  Spin up an isolated git worktree for a parallel Claude session (collision-free).
  See docs/superpowers/plans/2026-06-30-concurrency-playbook.md.

.DESCRIPTION
  1 session = 1 worktree = 1 branch = 1 DB = 1 port. Creates a worktree under
  .worktrees/<branch> off the integration branch, junctions the heavy immutable
  deps from the main checkout (same composer.lock — do NOT run composer install
  inside a worktree), writes an isolated .env (own DB + APP_URL port), creates +
  migrates + seeds the DB, and reports how to serve it.

.EXAMPLE
  scripts/new-worktree.ps1 -Branch batch-9-khidmat-nasihat -Port 8778
  scripts/new-worktree.ps1 -Branch batch-10-slot-calendar -Port 8779
#>
param(
  [Parameter(Mandatory = $true)][string]$Branch,
  [string]$Db,
  [int]$Port = 8778,
  [string]$BaseBranch = "batch-7-rbac"
)

$ErrorActionPreference = "Stop"
$root = (git rev-parse --show-toplevel)
Set-Location $root

if (-not $Db) { $Db = "iguaman_2in1_" + ($Branch -replace '[^a-zA-Z0-9]', '_') }
$wtPath = Join-Path $root ".worktrees/$Branch"

Write-Host "== worktree: .worktrees/$Branch  (branch $Branch off $BaseBranch)" -ForegroundColor Cyan
git worktree add $wtPath -b $Branch $BaseBranch

# Junction the big gitignored deps from main (instant, shared, read-only in practice).
foreach ($d in @("vendor", "node_modules", "public/build")) {
  $target = Join-Path $root $d
  $link = Join-Path $wtPath $d
  if ((Test-Path $target) -and -not (Test-Path $link)) {
    $parent = Split-Path $link -Parent
    if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Force -Path $parent | Out-Null }
    cmd /c mklink /J "`"$link`"" "`"$target`"" | Out-Null
    Write-Host "  junction $d -> main"
  }
}

# Writable storage framework dirs (not shared — isolated per worktree).
foreach ($s in @("framework/cache/data", "framework/sessions", "framework/views", "logs")) {
  New-Item -ItemType Directory -Force -Path (Join-Path $wtPath "storage/$s") | Out-Null
}

# Isolated .env: copy main's, override DB + APP_URL port.
$env_main = Join-Path $root ".env"
$env_wt = Join-Path $wtPath ".env"
$envText = Get-Content $env_main -Raw
$envText = $envText -replace '(?m)^DB_DATABASE=.*$', "DB_DATABASE=$Db"
$envText = $envText -replace '(?m)^APP_URL=.*$', "APP_URL=http://127.0.0.1:$Port"
Set-Content -Path $env_wt -Value $envText -NoNewline

Set-Location $wtPath

# Create the isolated DB (Laragon root, no password) via PDO — php is on PATH.
Write-Host "== create database $Db" -ForegroundColor Cyan
php -r "`$p=new PDO('mysql:host=127.0.0.1','root','');`$p->exec('CREATE DATABASE IF NOT EXISTS ``$Db`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');echo 'ok';"
Write-Host ""

php artisan migrate --force
php artisan db:seed --force

Write-Host ""
Write-Host "READY -> $wtPath" -ForegroundColor Green
Write-Host "  DB:   $Db"
Write-Host "  serve: php artisan serve --port=$Port   (run from the worktree dir)"
Write-Host "  Open a NEW Claude session with this folder as cwd. Do NOT work here from the main session."
Write-Host ""
Write-Host "Cleanup when merged:  git worktree remove .worktrees/$Branch ; drop database $Db"
