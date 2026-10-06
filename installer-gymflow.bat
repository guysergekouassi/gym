@echo off
title Installation de GymFlow
cd /d "%~dp0"
rem Premiere installation chez le client : a lancer UNE seule fois.
where php >nul 2>&1 || (echo PHP est introuvable. Installez Laragon ou PHP 8.3 d abord : voir docs\DEPLOIEMENT.md & pause & exit /b 1)
if exist ".env" (echo GymFlow est deja installe sur ce PC. & pause & exit /b 0)
copy ".env.production.exemple" ".env" >nul
php artisan key:generate --force
if not exist "database\database.sqlite" type nul > "database\database.sqlite"
php artisan migrate --force --seed
php artisan storage:link
call "%~dp0creer-raccourci-bureau.bat" /silencieux
echo.
echo Installation terminee.
echo Comptes : admin@gymflow.local et caisse@gymflow.local, mot de passe provisoire ChangeMoi!2026 (a changer a la 1re connexion).
echo Un raccourci GymFlow a ete ajoute sur le bureau.
echo Lancez maintenant activer-demarrage-auto.bat puis demarrer-gymflow.bat
pause
