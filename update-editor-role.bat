@echo off
echo ========================================
echo   Update Editor Role Configuration
echo ========================================
echo.

echo [1/4] Running database seeder...
php artisan db:seed --class=RolePermissionSeeder
echo.

echo [2/4] Clearing configuration cache...
php artisan config:clear
echo.

echo [3/4] Clearing application cache...
php artisan cache:clear
echo.

echo [4/4] Clearing view cache...
php artisan view:clear
echo.

echo ========================================
echo   Editor Role Updated Successfully!
echo ========================================
echo.
echo Editor role now has:
echo   - Full access to data management (Quota, PO, Master Data, Reports)
echo   - Read-only access to administrator section (Users, Roles, Permissions)
echo.
echo Please test by logging in as an editor user.
echo.
pause
