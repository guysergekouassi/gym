@echo off
rem Lance GymFlow automatiquement a chaque ouverture de session Windows (sans droits administrateur).
set "CIBLE=%~dp0demarrer-gymflow.bat"
set "ICONE=%~dp0public\favicon.ico"
set "RACCOURCI=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\GymFlow.lnk"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut($env:RACCOURCI); $s.TargetPath=$env:CIBLE; $s.WorkingDirectory=(Split-Path $env:CIBLE); $s.IconLocation=$env:ICONE; $s.WindowStyle=7; $s.Save()"
if exist "%RACCOURCI%" (echo Demarrage automatique ACTIVE : GymFlow se lancera a chaque allumage du PC.) else (echo Echec : raccourci non cree.)
pause
