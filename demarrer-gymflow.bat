@echo off
title GymFlow - ne pas fermer cette fenetre
cd /d "%~dp0"
echo.
echo  GymFlow demarre. Laissez les deux fenetres noires ouvertes toute la journee.
echo.
php artisan optimize:clear >nul
rem Liaison avec la pointeuse : fenetre separee
start "GymFlow - Pointeuse" cmd /k php artisan pointeuse:ecouter
start "" "http://127.0.0.1:8005"
rem Application accessible uniquement depuis ce PC (127.0.0.1) : rien n est ouvert sur le reseau
php artisan serve --host=127.0.0.1 --port=8005
pause
