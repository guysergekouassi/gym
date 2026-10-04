@echo off
rem Lance l application automatiquement a chaque ouverture de session Windows (sans droits administrateur).
set "CIBLE=%~dp0demarrer.bat"
set "RACCOURCI=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\Gestion salle.lnk"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut($env:RACCOURCI); $s.TargetPath=$env:CIBLE; $s.WorkingDirectory=(Split-Path $env:CIBLE); $s.WindowStyle=7; $s.Save()"
if exist "%RACCOURCI%" (echo Demarrage automatique ACTIVE : l application se lancera a chaque allumage du PC.) else (echo Echec : raccourci non cree.)
pause
