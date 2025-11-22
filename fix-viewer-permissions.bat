@echo off
echo ========================================
echo   FIX VIEWER ROLE PERMISSIONS
echo ========================================
echo.

echo [1/4] Running RolePermissionSeeder...
php artisan db:seed --class=RolePermissionSeeder
echo.

echo [2/4] Clearing cache...
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
echo.

echo [3/4] Optimizing application...
php artisan optimize
echo.

echo ========================================
echo   DONE!
echo ========================================
echo.
echo Next steps:
echo 1. Log out from the viewer account (Annisa)
echo 2. Log back in
echo 3. Try visiting /admin/users (should be 403 Forbidden)
echo 4. The Administration menu should NOT appear
echo.
pause
