@echo off
echo Starting npm run dev...
start cmd /k "npm run dev --host"

echo Starting php artisan serve...
start cmd /k "php artisan serve"

echo Both tasks have been started.
exit
