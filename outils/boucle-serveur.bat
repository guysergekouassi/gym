@echo off
rem Relance l application si elle s arrete (erreur, fermeture accidentelle...).
cd /d "%~dp0.."
:boucle
php artisan serve --host=127.0.0.1 --port=8005
echo GymFlow s est arrete. Redemarrage dans 5 secondes...
timeout /t 5 /nobreak >nul
goto boucle
