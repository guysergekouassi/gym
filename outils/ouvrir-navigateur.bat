@echo off
rem Ouvre l application dans une fenetre dediee de Chrome (ou Edge), avec impression directe :
rem chaque ticket part sur l imprimante PAR DEFAUT de Windows, sans fenetre de confirmation.
rem Si l imprimante est eteinte, Windows garde le ticket en file et l imprime a son retour.
set "URL=http://127.0.0.1:8005"
set "PROFIL=%LOCALAPPDATA%\GestionSalle\navigateur"
set "NAV="
for %%N in ("%ProgramFiles%\Google\Chrome\Application\chrome.exe" "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe") do (
    if not defined NAV if exist %%N set "NAV=%%~N"
)

rem Ni Chrome ni Edge : navigateur par defaut (la fenetre d impression s affichera)
if not defined NAV (start "" "%URL%" & exit /b 0)

start "" "%NAV%" --user-data-dir="%PROFIL%" --kiosk-printing --no-first-run --start-maximized --app="%URL%"
exit /b 0
