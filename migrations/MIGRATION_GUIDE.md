# PHASE 0: Database Setup - Migration Guide

## Migration Files Created

All migration files are in `/migrations/` folder and must be executed in order:

1. **2026_03_18_01_enhance_users_table.sql**
   - Adds email verification columns
   - Adds password reset token columns
   - Adds profile photo column
   - Creates indexes for token lookups

2. **2026_03_18_02_enhance_appointments_table.sql**
   - Adds cancellation reason
   - Adds sample status tracking
   - Adds technician assignment
   - Adds home collection fields
   - Creates performance indexes

3. **2026_03_18_03_create_notes_reviews_payments_tables.sql**
   - Creates `appointment_notes` table for admin notes
   - Creates `reviews` table for patient ratings
   - Creates `payments` table for transaction tracking

4. **2026_03_18_04_create_technicians_samples_referrals_tables.sql**
   - Creates `technicians` table for lab staff
   - Creates `sample_tracking` table for workflow
   - Creates `doctor_referrals` table

5. **2026_03_18_05_create_packages_slots_tables.sql**
   - Creates `packages` table for test bundles
   - Creates `package_tests` for package-test association
   - Creates `appointment_slots` for slot management
   - Inserts sample packages and appointment slots

## How to Apply Migrations

### Option 1: Using PHP Script (Recommended)
```bash
# From project root directory
php run_migrations.php
```

Or access from browser:
```
http://localhost/DiagnoSys/run_migrations.php
```

### Option 2: Using phpMyAdmin
1. Open phpMyAdmin
2. Select the `diagnosys_db` database
3. Go to SQL tab
4. Copy-paste each migration file content in order
5. Execute each one

### Option 3: Using MySQL Command Line
```bash
mysql -u root -p diagnosys_db < migrations/2026_03_18_01_enhance_users_table.sql
mysql -u root -p diagnosys_db < migrations/2026_03_18_02_enhance_appointments_table.sql
mysql -u root -p diagnosys_db < migrations/2026_03_18_03_create_notes_reviews_payments_tables.sql
mysql -u root -p diagnosys_db < migrations/2026_03_18_04_create_technicians_samples_referrals_tables.sql
mysql -u root -p diagnosys_db < migrations/2026_03_18_05_create_packages_slots_tables.sql
```

## Database Schema After Migrations

### Enhanced Tables:
- **users**: +5 new columns (email verification, password reset, profile photo)
- **appointments**: +7 new columns (cancellation, sample tracking, technician, home collection)

### New Tables:
- **appointment_notes**: Admin notes on appointments
- **reviews**: Patient reviews and ratings
- **payments**: Payment transaction records
- **technicians**: Lab staff information
- **sample_tracking**: Sample collection workflow
- **doctor_referrals**: Doctor referral information
- **packages**: Test bundles/packages
- **package_tests**: Package-test associations
- **appointment_slots**: Available appointment time slots

## Total Table Count After Phase 0: 17 tables

## What's Included in Migrations

- ✅ Column additions and enhancements
- ✅ New table creation with proper indexes
- ✅ Foreign key constraints
- ✅ Sample data (packets and slots)
- ✅ Timestamp tracking fields
- ✅ Status enums for workflow management

## Next Steps After Phase 0

After successful migration execution:
1. Verify all tables exist in phpMyAdmin
2. Verify all columns are present
3. Check sample data (packages, slots) are inserted
4. Commit migrations to git
5. **Ready for PHASE 1: Gisan's Frontend Implementation**

## Troubleshooting

If migrations fail:
1. Check database connection in `/config/init.php`
2. Ensure database user has CREATE TABLE privileges
3. Check for syntax errors in migration SQL
4. Try executing each migration individually
5. Check MySQL error logs for detailed messages

## Rollback (If Needed)

To remove all new tables:
```sql
DROP TABLE IF EXISTS appointment_slots;
DROP TABLE IF EXISTS package_tests;
DROP TABLE IF EXISTS packages;
DROP TABLE IF EXISTS doctor_referrals;
DROP TABLE IF EXISTS sample_tracking;
DROP TABLE IF EXISTS technicians;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS appointment_notes;
```

Then revert column changes with ALTER TABLE ... DROP COLUMN commands.
