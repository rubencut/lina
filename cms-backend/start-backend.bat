@echo off
cd /d "%~dp0"
set PORT=9000
:checkPort
netstat -ano | findstr ":%PORT%" >nul
if %errorlevel% equ 0 (
  set /a PORT+=1
  if %PORT% leq 9010 goto checkPort
  echo No free port found from 9000 to 9010.
  exit /b 1
)
echo Starting backend on http://127.0.0.1:%PORT%
php artisan serve --host=127.0.0.1 --port=%PORT%
