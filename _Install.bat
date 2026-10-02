@echo off
setlocal
cd /d "%~dp0"

echo ========================================
echo  Installing project dependencies
echo ========================================
echo.

REM --- Check PHP ---
where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP not found in PATH. Install PHP 8.2+ and try again.
    goto :fail
)
call php -v

REM --- Check Composer ---
where composer >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Composer not found in PATH. Install Composer from https://getcomposer.org/ and try again.
    goto :fail
)

REM --- Check Node / npm ---
where node >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Node.js not found in PATH. Install Node.js LTS and try again.
    goto :fail
)
where npm >nul 2>nul
if errorlevel 1 (
    echo [ERROR] npm not found in PATH. Reinstall Node.js and try again.
    goto :fail
)
call node -v
call npm -v
echo.

REM --- PHP dependencies (Laravel) ---
echo [1/2] Running composer install...
call composer install --no-interaction --prefer-dist
if errorlevel 1 (
    echo [ERROR] composer install failed.
    goto :fail
)
echo.

REM --- JS dependencies (Vite / Tailwind) ---
echo [2/2] Running npm install...
call npm install
if errorlevel 1 (
    echo [ERROR] npm install failed.
    goto :fail
)
echo.

echo ========================================
echo  All dependencies installed successfully.
echo ========================================
goto :done

:fail
echo.
echo [FAILED] See errors above.
goto :done

:done
echo.
echo Press any key to close this window...
pause
endlocal
exit /b 0
