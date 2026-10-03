@echo off
rem A lancer UNE fois : clic droit > Executer en tant qu administrateur.
rem Autorise la pointeuse a joindre GymFlow, uniquement depuis le reseau local de la salle.
netsh advfirewall firewall delete rule name="GymFlow 8005" >nul 2>&1
netsh advfirewall firewall add rule name="GymFlow 8005" dir=in action=allow protocol=TCP localport=8005 profile=private remoteip=localsubnet
echo.
echo Regle de pare-feu ajoutee (reseau prive, sous-reseau local uniquement).
pause
