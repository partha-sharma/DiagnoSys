@echo off
REM Database Migration Batch Script
REM Execute all migrations in order

cd /d C:\xampp\htdocs\DiagnoSys\migrations

echo.
echo ================================
echo DiagnoSys Database Migrations
echo ================================
echo.

echo [1/5] Applying: enhance_users_table.sql
mysql -u root diagnosys_db --execute="SOURCE 2026_03_18_01_enhance_users_table.sql"

echo [2/5] Applying: enhance_appointments_table.sql
mysql -u root diagnosys_db --execute="SOURCE 2026_03_18_02_enhance_appointments_table.sql"

echo [3/5] Applying: create_notes_reviews_payments_tables.sql
mysql -u root diagnosys_db --execute="SOURCE 2026_03_18_03_create_notes_reviews_payments_tables.sql"

echo [4/5] Applying: create_technicians_samples_referrals_tables.sql
mysql -u root diagnosys_db --execute="SOURCE 2026_03_18_04_create_technicians_samples_referrals_tables.sql"

echo [5/5] Applying: create_packages_slots_tables.sql
mysql -u root diagnosys_db --execute="SOURCE 2026_03_18_05_create_packages_slots_tables.sql"

echo.
echo ================================
echo All migrations completed!
echo ================================
echo.
pause
