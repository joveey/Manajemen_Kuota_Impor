@echo off
echo ========================================
echo   UPDATE ALL ROLE PERMISSIONS
echo ========================================
echo.
echo Updating permissions for all roles:
echo - Admin: Full access (no change)
echo - Manager: Administration + VIEW Data
echo - Editor: Manage Data only
echo - Viewer: VIEW Data only
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
echo 1. Logout dari semua akun
echo 2. Login kembali
echo 3. Test permissions:
echo.
echo    MANAGER:
echo    - Bisa akses Administration (Users, Roles, Permissions)
echo    - Bisa VIEW data (Quota, PO, Master Data, Reports)
echo    - TIDAK bisa edit data
echo.
echo    EDITOR:
echo    - TIDAK bisa akses Administration
echo    - Bisa manage data (Create, Edit, Delete)
echo.
echo    VIEWER:
echo    - TIDAK bisa akses Administration
echo    - Bisa VIEW data (read-only)
echo.
echo Lihat dokumentasi lengkap di: FINAL_ROLE_PERMISSIONS.md
echo.
pause
