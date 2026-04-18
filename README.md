# DiagnoSys

DiagnoSys is a PHP and MySQL based diagnostic lab management system for booking appointments, managing tests and packages, handling payments, uploading reports, and organizing patient, admin, and technician workflows.

## Overview

The application is organized by role so the codebase stays easier to maintain:

- Public visitors use the landing page and authentication pages
- Patients book tests, manage profiles, view invoices, and track payments
- Admin users manage rooms, appointments, tests, packages, finance, and users
- Technicians upload and manage diagnostic reports

## Tech Stack

- PHP
- MySQL
- HTML, CSS, and JavaScript
- XAMPP or another local PHP stack for development

## Features

- Patient registration, login, email verification, and password recovery
- Test booking with packages, coupons, and slot management support
- Patient dashboard for appointments, invoices, payments, reviews, and profile updates
- Admin dashboard for managing rooms, tests, appointments, packages, finance, and users
- Technician dashboard for uploading test results
- JSON helper endpoints for the booking flow

## Folder Structure

- `auth/` - login, registration, logout, password reset, and verification pages
- `patient/` - patient-facing dashboard, booking, payments, profile, reviews, and reports
- `admin/` - admin dashboards and management pages
- `technician/` - technician dashboard and upload workflow
- `api/` - JSON endpoints used by booking and coupon validation features
- `assets/` - CSS and static assets
- `config/` - database connection and application bootstrap files
- `includes/` - shared helper functions and taxonomy utilities
- `partials/` - reusable layout components
- `migrations/` - SQL migration scripts
- `uploads/` - generated uploads and profile images

## Main Entry Points

- `index.php` - public landing page
- `auth/login.php` - login page
- `auth/register.php` - registration page
- `patient/dashboard.php` - patient dashboard
- `admin/index.php` - admin dashboard
- `technician/dashboard.php` - technician dashboard

## Installation

1. Copy the project into your local web server directory, for example `C:\xampp\htdocs\DiagnoSys`.
2. Create a MySQL database named `diagnosys` or update the connection settings in `config/db.php`.
3. Import `diagnosys_db.sql` into the database.
4. Run any scripts inside `migrations/` if your local schema needs to be aligned with the application code.
5. Open the site in your browser through the local server, for example `http://localhost/DiagnoSys/`.

## Common Workflow

### Patient flow

1. Register or log in through `auth/`.
2. Book an appointment from the patient dashboard.
3. Select tests or packages and apply coupons if available.
4. Complete payment and view the booking confirmation or invoice.
5. Update profile details and review uploaded results.

### Admin flow

1. Log in as an admin user.
2. Manage appointments, tests, packages, rooms, finance, and users.
3. Assign room and slot schedules as needed.

### Technician flow

1. Log in as a technician.
2. Open the technician dashboard.
3. Upload report files for assigned appointments.

## API Endpoints

The booking pages use lightweight JSON endpoints from `api/`:

- `api/get-package-details.php`
- `api/get-packages.php`
- `api/get-technicians.php`
- `api/get-available-slots.php`
- `api/validate-coupon.php`

## Uploads

- Patient profile photos are stored under `uploads/profile/`
- Other generated uploads stay under `uploads/`
- Image files and profile upload files are ignored by Git so local user content is not committed accidentally

## Configuration Notes

- Shared bootstrapping and session setup live in `config/init.php`
- Shared helper functions live in `includes/functions.php`
- Root public links now point into the role-based folders, so keep paths relative to each module when adding new pages

## Project Status

The codebase has been reorganized into role-based modules to make future maintenance, navigation, and feature growth easier.