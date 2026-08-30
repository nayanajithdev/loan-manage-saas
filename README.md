# Loan Management SaaS

PHP + MySQL multi-tenant loan management system for small lending businesses.

## Features
- Dark dashboard UI with sidebar + top bar
- Customer management
- Loan creation with auto installment count from timeframe
- Collection page with due list, overdue visibility, and record form
- Collection history page
- Auto-refresh (AJAX polling) for key dashboard/collection sections
- Multi-user login with roles:
  - `superadmin` (SaaS Admin)
  - `admin` (Tenant Owner)
  - `collector` (Collector)
- SaaS Admin tenant approval dashboard
- Tenant registration with pending approval flow

## Role rules
- SaaS Admin uses the secret admin URL and manages tenants.
- Tenants use the normal login and registration pages.
- Tenant owners can add/delete tenant users, but cannot access SaaS Admin features.
- Collector permissions are configurable by the owner/manager.

## Tech stack
- HTML
- CSS
- JavaScript
- PHP 8+
- MySQL

## Setup
1. Create database tables:
   - Open `db/schema.sql`
   - Run it in phpMyAdmin or MySQL CLI
2. Configure DB connection in `config/app.php` if needed.
3. Start Apache + MySQL in XAMPP.
4. Open:
   - Tenant login: `http://localhost/loan-manage-saas/login.php`
   - Tenant register: `http://localhost/loan-manage-saas/register.php`
   - SaaS Admin: `http://localhost/loan-manage-saas/f3fd7t3`
5. If this is first startup and no superadmin exists:
   - Open the SaaS Admin URL.
   - Create the first SaaS Admin account.

## Next phase ideas
- Edit/delete customers and loans
- Print receipt after collection
- Reports by date range
- Penalty/late-fee rules

## Production
1. Copy `.env.example` to `.env`.
2. Set `APP_ENV=production`, `APP_DEBUG=0`, and `FORCE_HTTPS=1`.
3. Set `APP_URL` to the HTTPS domain.
4. Change `OWNER_SECRET_PATH` before launch.
5. Use a dedicated MySQL user with a strong password instead of `root`.