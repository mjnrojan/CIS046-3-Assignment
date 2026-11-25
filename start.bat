@echo off
REM ======================================================================
REM  Banana Puzzle Arena - one-click launcher for demos
REM
REM  The PHP built-in server serves BOTH parts from one process:
REM    frontend  -> http://localhost:PORT/frontend/   (HTML, CSS, JS)
REM    backend   -> http://localhost:PORT/api/        (PHP JSON endpoints)
REM  One origin means no CORS setup and the session cookie just works.
REM
REM  Usage:
REM    start.bat              start on port 8000
REM    start.bat 9000         start on another port
REM    start.bat --reset      WIPE the database, recreate tables and demo
REM                           accounts, then start (asks for confirmation)
REM ======================================================================

setlocal
cd /d "%~dp0"

set PORT=8000
set RESET=0
if /I "%~1"=="--reset" (set RESET=1) else if not "%~1"=="" set PORT=%~1
if /I "%~2"=="--reset" set RESET=1

where php >nul 2>nul
if errorlevel 1 (
    echo PHP was not found on PATH. Install PHP or add C:\xampp\php to PATH.
    pause
    exit /b 1
)

if not exist ".env" (
    echo No .env file found. Copying .env.example to .env - edit it with your DB details.
    copy ".env.example" ".env" >nul
)

if "%RESET%"=="1" (
    echo.
    echo WARNING: --reset drops ALL tables and recreates them. All players and scores are deleted.
    set /p CONFIRM=Type YES to continue:
)
if "%RESET%"=="1" (
    if /I not "%CONFIRM%"=="YES" (
        echo Reset cancelled.
        exit /b 1
    )
    php sql\migrate.php
    if errorlevel 1 (
        pause
        exit /b 1
    )
)

php tools\preflight.php
if errorlevel 1 (
    pause
    exit /b 1
)

echo.
echo Starting Banana Puzzle Arena on http://localhost:%PORT%/frontend/index.html
echo Demo logins: pilot_player / password123  and  rival_player / password123
echo Press Ctrl+C to stop the server.
echo.

REM Open the browser shortly after the server starts.
REM "ping -n 3" is a ~2 second pause that works in any console (timeout.exe does not).
start "" /b cmd /c "ping -n 3 127.0.0.1 >nul & start http://localhost:%PORT%/frontend/index.html"

php -S localhost:%PORT% -t .
