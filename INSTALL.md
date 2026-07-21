# Installing Inspectra

A step-by-step guide for getting Inspectra running from scratch on a local machine. This
assumes a typical XAMPP-style stack (Apache + MySQL/MariaDB + PHP), but the steps translate
directly to any LAMP/WAMP setup.

## 1. Prerequisites

- PHP 7.4 or 8.x with the `pdo_mysql`, `curl`, `mbstring`, and `gd` extensions (all enabled by
  default in XAMPP)
- MySQL or MariaDB
- [Composer](https://getcomposer.org/) (for PHPMailer)
- Apache with `mod_rewrite` enabled (used by `.htaccess`)

## 2. Get the code

Place the project inside your web server's document root. On XAMPP this is typically:

```
C:\xampp\htdocs\inspectra
```

If you cloned this from git, make sure you're inside that `inspectra/` folder for every command
below.

## 3. Install PHP dependencies

```bash
composer install
```

This creates the `vendor/` folder (PHPMailer and the autoloader). It is gitignored on purpose —
always regenerate it with this command rather than expecting it to already be there.

## 4. Create the database

Start MySQL, then import the single schema file. It creates the database if it doesn't exist and
is safe to re-run — it will never drop or overwrite existing data:

```bash
mysql -u root -p < database/inspectra_db_complete_schema.sql
```

(If your MySQL root user has no password, as is typical on a fresh XAMPP install, omit `-p`.)

This creates every table the app uses and seeds two accounts you can log in with immediately —
see step 6. It's the single, up-to-date, authoritative schema for a fresh install. If you're
instead trying to catch up an **existing**, older Inspectra database that's missing newer tables
or columns, the simplest fix is to drop that database and re-import this file fresh.

## 5. Configure the application

Copy the example config and fill in your own values:

```bash
cp config.example.php config.php
```

Then edit `config.php`:

| Setting | What to put |
| --- | --- |
| `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` | Your MySQL connection details (defaults match a stock XAMPP install: `localhost` / `root` / empty password / `inspectra_db`) |
| `APP_URL` | The base URL you'll access the app at, e.g. `http://localhost/inspectra` |
| `APP_ENV` | `development` locally; `production` once actually deployed (this also controls whether PHP errors are displayed) |
| `JWT_SECRET` | Any long random string — used for session/token signing |
| `STRIPE_*` / `PAYSTACK_*` | Test-mode API keys from your Stripe/Paystack dashboard if you want to exercise the payment flows; the app runs fine without real keys, you just can't complete a live payment |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Only needed if you want to test "Sign in with Google" |
| `SMTP_*` | An SMTP account (e.g. a Gmail address with an [app password](https://myaccount.google.com/apppasswords)) if you want outgoing email to actually send; without it, emails simply fail silently and in-app notifications still work |

**`config.php` is gitignored — never commit it.** It's the only file that should ever hold real
credentials; `config.example.php` is the one that's safe to share.

## 6. Log in

Visit `APP_URL` in your browser (e.g. `http://localhost/inspectra/index.php`) and sign in with
one of the two seeded accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@inspectra.com` | `Admin@1234` |
| Customer | `kofi@example.com` | `Customer@1234` |

**Change or remove these before any real/production use.**

## 7. Uploads folder

`uploads/` (vehicle photos, inspection evidence, signatures, dispute attachments, avatars) needs
to be writable by the web server. The subfolders are created automatically the first time
something is uploaded — you only need to make sure the top-level `uploads/` directory itself has
write permission. On XAMPP for Windows this is normally already the case; on Linux you may need:

```bash
chmod -R 775 uploads/
```

## 8. Try it out

A good smoke test, in order:
1. Browse vehicles as a guest at `/public/browse.php` (empty until an admin adds a vehicle)
2. Log in as the admin and add a vehicle under **Fleet → Vehicles**
3. Log out, register a new customer account (or use the seeded `kofi@example.com`)
4. Book the vehicle you just added and make a payment (Paystack test mode, or skip if you didn't
   configure keys)
5. Log back in as admin and check the booking appears under **Bookings**

## Troubleshooting

- **Blank white page** — check `APP_ENV` is `development` so errors actually display, and check
  Apache's error log; the most common cause is `config.php` not existing yet (step 5) or a typo
  in the database credentials.
- **"Unknown column" / SQL errors** — your database predates a schema change; drop it and
  re-import `database/inspectra_db_complete_schema.sql` fresh (step 4).
- **Payments don't complete** — this needs real (test-mode) Stripe/Paystack keys in `config.php`;
  without them the checkout step will fail, which is expected.
- **No emails arrive** — SMTP isn't configured, or the credentials are wrong; in-app notifications
  still work independently of email, so this doesn't block using the app.
