@echo off
title Gestion de la salle
cd /d "%~dp0"

where php >nul 2>&1 || (echo PHP est introuvable sur ce PC. Voir docs\DEPLOIEMENT.md & pause & exit /b 1)

rem Ce qui tourne deja : l application (port 8005) et la liaison pointeuse (sa fenetre).
rem Chacune est verifiee a part : si l application a ete lancee a la main
rem (php artisan serve --port=8005), la liaison pointeuse est quand meme demarree.
set "SERVEUR=0"
set "POINTEUSE=0"
netstat -ano | findstr ":8005 " | findstr LISTENING >nul && set "SERVEUR=1"
tasklist /v /fi "WINDOWTITLE eq Salle - Pointeuse*" | findstr /i "cmd.exe" >nul && set "POINTEUSE=1"

if "%SERVEUR%%POINTEUSE%"=="11" (call "%~dp0outils\ouvrir-navigateur.bat" & exit /b 0)

echo Demarrage de l application...
if "%SERVEUR%"=="0" (
    php artisan optimize:clear >nul
    php artisan salle:sauvegarder
)

rem Fenetres reduites, relancees automatiquement si elles s arretent
if "%SERVEUR%"=="0" start "Salle - Application (ne pas fermer)" /min cmd /c "%~dp0outils\boucle-serveur.bat"
if "%POINTEUSE%"=="0" start "Salle - Pointeuse (ne pas fermer)" /min cmd /c "%~dp0outils\boucle-pointeuse.bat"

timeout /t 4 /nobreak >nul
rem Fenetre de l application avec impression directe des tickets (voir outils\ouvrir-navigateur.bat)
call "%~dp0outils\ouvrir-navigateur.bat"
exit /b 0
