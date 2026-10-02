@echo off
setlocal
cd /d "%~dp0"

echo ========================================
echo  Running database migrations
echo ========================================
echo.
echo Usage: _Migrate.bat [--fresh] [--seed] [--rollback]
echo   no args        = php artisan migrate
echo   --fresh        = php artisan migrate:fresh
echo   --fresh --seed = php artisan migrate:fresh --seed
echo   --rollback     = php artisan migrate:rollback
echo.

REM --- Check PHP ---
where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP not found in PATH. Install PHP 8.2+ and try again.
    goto :fail
)

REM --- Check artisan + vendor ---
if not exist "artisan" (
    echo [ERROR] artisan file not found. Run this from the Laravel project root.
    goto :fail
)
if not exist "vendor\autoload.php" (
    echo [ERROR] vendor\autoload.php not found. Run _Install.bat first.
    goto :fail
)

REM --- Ensure .env exists ---
if not exist ".env" (
    if exist ".env.example" (
        echo [.env missing] Copying .env.example to .env...
        copy /y ".env.example" ".env" >nul
        echo Generating app key...
        call php artisan key:generate --ansi
    ) else (
        echo [ERROR] Neither .env nor .env.example found.
        goto :fail
    )
)

REM --- Ensure sqlite file exists (default DB is sqlite) ---
if not exist "database\database.sqlite" (
    echo [sqlite] Creating empty database\database.sqlite...
    if not exist "database" mkdir "database"
    type nul > "database\database.sqlite"
)
echo.

REM --- Pick migrate command from args ---
if "%~1"=="--rollback" (
    echo Running: php artisan migrate:rollback
    call php artisan migrate:rollback
    if errorlevel 1 goto :fail
    goto :success
)

if "%~1"=="--fresh" (
    if "%~2"=="--seed" (
        echo Running: php artisan migrate:fresh --seed
        call php artisan migrate:fresh --seed
    ) else (
        echo Running: php artisan migrate:fresh
        call php artisan migrate:fresh --force
    )
    if errorlevel 1 goto :fail
    goto :success
)

if "%~1"=="--seed" (
    echo Running: php artisan migrate --seed
    call php artisan migrate --seed --force
    if errorlevel 1 goto :fail
    goto :success
)

echo Running: php artisan migrate
call php artisan migrate --force
if errorlevel 1 goto :fail

:success
echo.
echo ========================================
echo  Migrations completed successfully.
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
