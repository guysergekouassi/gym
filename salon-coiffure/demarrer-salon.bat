@echo off
REM Lance la caisse SalonFlow sur ce PC (aucune connexion internet nécessaire)
cd /d "%~dp0"

REM Démarre le serveur local dans une fenêtre réduite (ne pas la fermer pendant la journée)
start "SalonFlow - serveur (ne pas fermer)" /min php artisan serve --host=127.0.0.1 --port=8000

REM Laisse 3 secondes au serveur pour démarrer
timeout /t 3 /nobreak >nul

REM Ouvre Chrome en mode application. --kiosk-printing : le ticket part directement
REM sur l'imprimante par défaut de Windows, sans boîte de dialogue
set CHROME="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="%LocalAppData%\Google\Chrome\Application\chrome.exe"

if exist %CHROME% (
    start "" %CHROME% --kiosk-printing --app=http://127.0.0.1:8000 --start-maximized
) else (
    start "" http://127.0.0.1:8000
)
