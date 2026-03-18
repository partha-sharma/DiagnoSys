@echo off
REM =================================================================
REM DiagnoSys Database Migration - Complete Batch Runner
REM =================================================================
REM This script applies all database migrations for Phase 0
REM Run this from Command Prompt or PowerShell
REM =================================================================

cd /d "C:\xampp\htdocs\DiagnoSys\migrations"

echo.
echo ============================================================
echo   DiagnoSys - PHASE 0: Database Migration
echo ============================================================
echo.

echo [*] Checking MySQL availability...
mysql -u root -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] MySQL is not running or not found!
    echo Please start MySQL/MariaDB server and try again.
    pause
    exit /b 1
)

echo [OK] MySQL is running
echo.

echo [*] Applying complete migration...
echo.

mysql -u root diagnosys_db < "2026_03_18_00_COMPLETE_MIGRATION.sql"

if errorlevel 1 (
    echo.
    echo [ERROR] Migration failed!
    pause
    exit /b 1
)

echo.
echo ============================================================
echo [SUCCESS] All migrations completed successfully!
echo ============================================================
echo.
echo Next Step: GISAN - Frontend User Features Implementation
echo.
pause
