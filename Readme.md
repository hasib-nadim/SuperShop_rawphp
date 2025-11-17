# Supershop (ecom_php) — Developer Run Instructions

This document explains how to run the PHP application locally, how to provide environment configuration, and how to run the idempotent schema sync + seed script that creates initial admin and user accounts.

Prerequisites
- PHP 7.4+ with mysqli extension
- MySQL / MariaDB server
- Composer is not required for this project (no composer dependencies used)
- A terminal (bash) for running the built-in PHP server or running the seed script

1) Create a `.env` file

At the project root (`ecom_php/`), create a file named `.env`. Example contents:

```
# App
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_KEY=SomeRandomStringOr32+CharsSecret

# Database
DB_DRIVER=mysqli
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=your_db_password
DB_NAME=ecom_db

# Mail (optional)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=null

```

Notes:
- The application will read `.env` automatically via `app/config/env.php` unless `app/config/config.php` exists. Keys should be uppercase like above; the loader lowercases them when merging with defaults.
- If you prefer to maintain environment-specific PHP config, the seed script will write `app/config/config.php` from your effective environment when it runs.


2) Serve the app locally

From the project root (`ecom_php`) you can use PHP's built-in web server for development:

```bash
# serve from current directory on port 8080
php -S localhost:8080
```


You may also open `http://localhost:8080/app/seed.php` in the browser if you run the built-in PHP server (see next step). The CLI run is preferred when automating or when the web root is not pointing at the project root.

Then open `http://localhost:8080/admin` to access the admin area (login with seeded credentials below).

5) Seeded accounts

After the seed script runs, the following accounts are created if they don't already exist:
- super admin user: username `superadmin`, password `spadmin123` (role: superadmin)
- normal admin: username `admin`, password `admin123` (role: admin)
- regular user: email `user@example.com`, password `password`

Change these passwords after first login.

6) Troubleshooting & notes
- Database connection errors: verify `.env` DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD and that the MySQL server is reachable.
- If `app/seed.php` fails with permission errors writing `app/config/config.php`, ensure the user running the command has write permission to `app/config` (the seed script will create that folder if needed).
- The schema sync uses `app/database/db.sql`. The SQL file was written to be idempotent, but if you have a non-standard `users` or `adminuser` table already, review `app/database/db.sql` before running the seed on a production DB.
- To re-run the seed script safely, run `php app/seed.php` again — it only inserts the initial accounts when missing and will not drop existing data.

7) Optional: add `is_active` to `users` table

If the user restrict/unrestrict feature needs the `is_active` column and your existing `users` table lacks it, run the idempotent ALTER (test in a staging DB first):

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;
```

If your MySQL/MariaDB does not support `IF NOT EXISTS` for `ADD COLUMN`, use an information_schema check and `ALTER TABLE` accordingly.

8) Common commands summary

```bash
# serve
php -S localhost:8080
```

If you'd like, I can also:
- add a `.env.example` file to the repo
- add a CLI script `bin/sync-and-seed` to wrap the seed steps and print clearer logs
- add an idempotent migration that ensures `users.is_active` exists

---
References:
- env loader: `app/config/env.php`
- DB sync: `app/database/db.php`
- Seed script: `app/seed.php`
- Schema SQL: `app/database/db.sql`

# Project Structure

Below is a guided overview of this repository (the `ecom_php` project) with the most important folders and files and a one-line purpose for each. Use this as a quick orientation when exploring or extending the app.

- `admin/` — Admin UI pages and sub-sections
	- `index.php` — Admin dashboard (counts, recent items)
	- `login/` — Admin login pages
	- `adminuser/` — CRUD for admin users (index, new, edit, delete)
	- `category/` — Category CRUD pages
	- `product/` — Product CRUD (list, new, edit, delete, remove_image endpoint)
	- `orders/` — Orders listing (and linked view page)
	- `logout.php` — Admin logout helper (calls session cleanup)

- `app/` — Application core helpers and config
	- `config/env.php` — .env loader and `env()` helper; merges defaults and `app/config/config.php` when present
	- `config/config.php` — generated at seed time from `.env` (created by `app/seed.php`)
	- `database/db.php` — DB connection helpers, schema sync runner and simple helpers (exists_by, closeConnection)
	- `database/db.sql` — idempotent schema SQL used by `DB\syncSchema()`
	- `helpers/` — small helper classes (Request, Helpers, Session)
	- `seed.php` — script to sync schema and insert initial accounts (superadmin/admin/user)

- `partials/` — Reusable page partials
	- `head.php`, `header.php`, `footer.php`, `admin/nav.php` — used by `pageHead()` and `pageFooter()` from `_imports.php`

- `public/` — Public assets
	- `css/` — stylesheets (admin_dashboard.css, home.css, etc.)
	- `images/products/` — uploaded product images (ensure writable by web server)
	- `js/` — client-side scripts

- Root-level and helpers
	- `_imports.php` — central bootstrap; loads env, helpers, DB and exposes `pageHead()`, `pageFooter()`, `component()` and URL helpers
	- `admin/logout.php` — wrapper to log out admin sessions (calls `LogoutAdmin()` from Session helper)
	- `Readme.md` — this file (developer run instructions & structure)

Notes and conventions
- The codebase uses procedural PHP with mysqli. There is no framework or Composer dependency.
- Environment variables are read from `.env` into a `$config` that is then written to `app/config/config.php` by the seed script. The helper `env()` reads values from the effective config.
- Database changes are applied by executing `app/database/db.sql` via `DB\syncSchema()`; the SQL is written idempotently to be safe to re-run.

If you want, I can also:
- Generate a `.env.example` in the repo with the recommended variables.
- Move inline page styles into `public/css/admin_dashboard.css` for consistency.
- Add a short diagram (ASCII) of routes and their primary handlers.

