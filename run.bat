@echo off
cd /d "%~dp0"
echo ========================================================
echo   Starting VendFlow Website Server (MySQL + PHP)
echo ========================================================

:: 1. Free port 8000 if an old PHP server instance was left hanging
for /f "tokens=5" %%a in ('netstat -aon ^| findstr /r ":8000.*LISTENING"') do (
    echo Port 8000 was in use by PID %%a. Closing it...
    taskkill /F /PID %%a >nul 2>&1
)

:: 2. Check if MySQL daemon is running; start if not running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="1" (
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        echo Starting XAMPP MySQL daemon...
        start "" /b "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 2 /nobreak >nul
    ) else (
        echo [NOTE] Ensure your MySQL server is running on localhost:3306.
    )
)

echo Opening browser at http://localhost:8000 ...
start http://localhost:8000
echo Server is running at http://localhost:8000
echo Press Ctrl+C in this window to stop the PHP server.
echo.
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8000
) else (
    php -S localhost:8000
)
pause
