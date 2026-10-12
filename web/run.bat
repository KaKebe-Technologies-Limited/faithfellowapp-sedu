@echo off
setlocal

set PHP=C:\xampp\php\php.exe
set COMPOSER=C:\Users\SEDU\tools\composer.phar
set NODE=C:\Users\SEDU\tools\node\node.exe
set NPM=C:\Users\SEDU\tools\node\npm.cmd
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set DIR=C:\xampp\htdocs\faithfellow

cd /d %DIR%

echo.
echo ============================================================
echo  FaithFellows — Auto Setup
echo ============================================================

:: ── 1. Create database ──────────────────────────────────────
echo.
echo [1/7] Creating database "faithfellows" if not exists...
%MYSQL% -u root -e "CREATE DATABASE IF NOT EXISTS faithfellows CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Could not connect to MySQL. Make sure XAMPP MySQL is running.
    pause
    exit /b 1
)
echo       Done.

:: ── 2. Composer install ─────────────────────────────────────
echo.
echo [2/7] Installing PHP dependencies (composer install)...
%PHP% %COMPOSER% install --no-interaction --no-progress
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Composer install failed.
    pause
    exit /b 1
)
echo       Done.

:: ── 3. NPM install ──────────────────────────────────────────
echo.
echo [3/7] Installing Node dependencies (npm install)...
%NODE% %NPM% install --no-fund --no-audit
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: npm install failed.
    pause
    exit /b 1
)
echo       Done.

:: ── 4. Vite build ───────────────────────────────────────────
echo.
echo [4/7] Building frontend assets (npm run build)...
%NODE% %NPM% run build
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Vite build failed.
    pause
    exit /b 1
)
echo       Done.

:: ── 5. Run migrations ───────────────────────────────────────
echo.
echo [5/7] Running database migrations...
%PHP% artisan migrate --force
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Migrations failed.
    pause
    exit /b 1
)
echo       Done.

:: ── 6. Seed database ────────────────────────────────────────
echo.
echo [6/7] Seeding database (roles, super admin, demo data)...
%PHP% artisan db:seed --force
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Seeding failed.
    pause
    exit /b 1
)
echo       Done.

:: ── 7. Storage link & cache ─────────────────────────────────
echo.
echo [7/7] Linking storage and clearing caches...
%PHP% artisan storage:link
%PHP% artisan config:clear
%PHP% artisan cache:clear
%PHP% artisan view:clear
%PHP% artisan route:clear
echo       Done.

:: ── Summary ─────────────────────────────────────────────────
echo.
echo ============================================================
echo  All done! Start the dev server:
echo.
echo    %PHP% artisan serve
echo.
echo  Then open: http://127.0.0.1:8000
echo.
echo  Super admin login:
echo    Email:    admin@faithfellows.app
echo    Password: ChangeMe!2026
echo ============================================================
echo.
pause
