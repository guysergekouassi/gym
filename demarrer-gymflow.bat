@echo off
title GymFlow
cd /d "%~dp0"

where php >nul 2>&1 || (echo PHP est introuvable sur ce PC. Voir docs\DEPLOIEMENT.md & pause & exit /b 1)

rem Deja lance ? On ouvre simplement le navigateur.
netstat -ano | findstr ":8005 " | findstr LISTENING >nul && (start "" "http://127.0.0.1:8005" & exit /b 0)

echo GymFlow demarre...
php artisan optimize:clear >nul
php artisan salle:sauvegarder

rem Deux fenetres reduites, relancees automatiquement si elles s arretent
start "GymFlow - Application (ne pas fermer)" /min cmd /c "%~dp0outils\boucle-serveur.bat"
start "GymFlow - Pointeuse (ne pas fermer)" /min cmd /c "%~dp0outils\boucle-pointeuse.bat"

timeout /t 4 /nobreak >nul
start "" "http://127.0.0.1:8005"
exit /b 0
