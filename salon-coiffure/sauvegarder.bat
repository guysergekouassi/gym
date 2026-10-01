@echo off
REM Copie la base de données (toutes les ventes) dans le dossier sauvegardes, datée du jour.
REM A lancer chaque soir, puis copier le dossier sur une clé USB de temps en temps.
cd /d "%~dp0"
if not exist sauvegardes mkdir sauvegardes
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set DATE=%%i
copy /y "database\database.sqlite" "sauvegardes\salonflow_%DATE%.sqlite" >nul
echo Sauvegarde creee : sauvegardes\salonflow_%DATE%.sqlite
pause
