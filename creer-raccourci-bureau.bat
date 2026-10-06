@echo off
rem Cree le raccourci GymFlow (logo de la salle) sur le bureau Windows.
rem Un double-clic dessus demarre GymFlow et ouvre sa fenetre.
set "CIBLE=%~dp0demarrer-gymflow.bat"
set "ICONE=%~dp0public\favicon.ico"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$b=[Environment]::GetFolderPath('Desktop'); $s=(New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $b 'GymFlow.lnk')); $s.TargetPath=$env:CIBLE; $s.WorkingDirectory=(Split-Path $env:CIBLE); $s.IconLocation=$env:ICONE; $s.WindowStyle=7; $s.Description='Ouvrir GymFlow'; $s.Save()"
if errorlevel 1 (echo Echec : raccourci non cree.) else (echo Raccourci GymFlow cree sur le bureau.)
if /i not "%~1"=="/silencieux" pause
