@echo off
setlocal

set PHP=C:\xampp\php\php.exe
set DIR=C:\xampp\htdocs\faithfellow

cd /d %DIR%

echo.
echo ============================================================
echo  FaithFellows Dev Server
echo  URL: http://127.0.0.1:8000
echo  Stop with Ctrl+C
echo ============================================================
echo.

%PHP% artisan serve --host=127.0.0.1 --port=8000
