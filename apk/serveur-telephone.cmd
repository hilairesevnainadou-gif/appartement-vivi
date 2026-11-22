@echo off
REM ======================================================================
REM  VIVI - rend la plateforme joignable par le telephone sur le Wi-Fi,
REM  quelle que soit l'adresse IP du PC ce jour-la.
REM  Le site reste sur http://localhost:8049 (DevLauncher) ; ce script
REM  ajoute seulement l'ecoute sur l'IP Wi-Fi du PC, port 8049.
REM  L'application (version de test) retrouve seule cette adresse.
REM ======================================================================
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1).IPv4Address.IPAddress"`) do set IP=%%i
if "%IP%"=="" (
    echo Aucune connexion Wi-Fi trouvee : connectez le PC au Wi-Fi puis relancez.
    pause
    exit /b 1
)
echo.
echo  Plateforme pour le telephone : http://%IP%:8049
echo  (le site reste sur http://localhost:8049)
echo  Laissez cette fenetre ouverte. Ctrl+C pour arreter.
echo.
cd /d "%~dp0..\plateforme"
C:\xampp\php\php.exe artisan serve --host=%IP% --port=8049 --no-reload
