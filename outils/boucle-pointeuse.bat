@echo off
rem Relance la liaison avec la pointeuse si elle s arrete.
cd /d "%~dp0.."
:boucle
php artisan pointeuse:ecouter
echo Liaison pointeuse arretee. Redemarrage dans 5 secondes...
timeout /t 5 /nobreak >nul
goto boucle
