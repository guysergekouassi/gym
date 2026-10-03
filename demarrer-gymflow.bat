@echo off
title GymFlow - ne pas fermer cette fenetre
cd /d "%~dp0"
echo.
echo  GymFlow demarre sur le reseau local (port 8005).
echo  Laissez cette fenetre ouverte pendant toute la journee.
echo.
php artisan optimize:clear >nul
start "" "http://127.0.0.1:8005"
php artisan serve --host=0.0.0.0 --port=8005
pause
