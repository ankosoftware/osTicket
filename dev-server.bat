@echo off
echo Starting osTicket Development Server...
echo.
echo Make sure you have:
echo 1. PHP 8.2+ installed and in PATH
echo 2. MySQL running with osTicket database
echo 3. include/ost-config.php configured
echo.
echo Server will be available at: http://localhost:8080
echo API v2 endpoints at: http://localhost:8080/api/v2/tickets.json
echo.
echo Press Ctrl+C to stop the server
echo.
php -S localhost:8080 -t . router.php
