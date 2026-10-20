# Installation Guide

These steps assume a local XAMPP environment on Windows, macOS, or Linux
(Ubuntu instructions included, since this matches a common student setup).

## 1. Requirements

- PHP 8.0 or newer with the `pdo_mysql` and `mbstring` extensions enabled
  (both are enabled by default in XAMPP)
- MySQL or MariaDB
- A web browser

## 2. Copy the project files

Copy the entire `pharmacy` folder into your XAMPP web root:

- Windows: `C:\xampp\htdocs\pharmacy`
- Linux/macOS: `/opt/lampp/htdocs/pharmacy` or your configured Apache
  document root

## 3. Create the database

1. Start Apache and MySQL from the XAMPP control panel.
2. Open phpMyAdmin (`http://localhost/phpmyadmin`).
3. Click **Import**, choose `database.sql` from this project, and run the
   import. This creates the `pharmacy_ims` database, all nine tables, two
   sample users (admin/staff), four sample medicines, and two sample boxes.

Alternatively, from a terminal:

```bash
mysql -u root -p < database.sql
```

## 4. Configure the database connection

Open `config/Database.php` and adjust the credentials if your MySQL setup
differs from the XAMPP default (`root` with no password):

```php
private string $host = 'localhost';
private string $dbName = 'pharmacy_ims';
private string $username = 'root';
private string $password = '';
```

## 5. Set the base URL

Open `config/config.php` and set `BASE_URL` to match where you placed the
project, including a trailing slash:

```php
define('BASE_URL', '/pharmacy/');
```

If you placed the project at the web root itself, use `'/'` instead.

## 6. Open the application

Visit `http://localhost/pharmacy/` in your browser. You should be redirected
to the login page automatically.

## 7. Log in

Use one of the sample accounts:

- Admin: `admin` / `admin123`
- Staff: `staff` / `staff123`

## 8. Verify everything works

A quick smoke test after installing:

1. Log in as `admin` — the Dashboard should show 4 sample products, 2
   sample boxes, and zero sales.
2. Go to **Products** and confirm the four sample medicines appear.
3. Go to **Stock In**, add stock to a medicine, and confirm the medicine's
   stock count increases on the Products page.
4. Go to **Sales → New Sale**, record a sale with a partial payment, and
   confirm it appears as "Partial" status on the Sales page and under
   **Debts**.
5. From **Debts**, click **Add Payment** and pay off the remaining balance —
   the status should automatically switch to "Paid".
6. Log out and log back in as `staff` to confirm the **Users** menu item is
   hidden and that visiting it directly redirects back to the dashboard with
   an access-denied message.

## Troubleshooting

**Blank page / "Database connection failed"** — Confirm MySQL is running
and that the credentials in `config/Database.php` are correct.

**"Call to undefined function mb_strlen()"** — The `mbstring` PHP extension
is not enabled. On Ubuntu: `sudo apt install php-mbstring` and restart
Apache. XAMPP ships with this enabled already.

**Sidebar links go to the wrong path** — Update `BASE_URL` in
`config/config.php` to match your actual install path.

**Login succeeds but pages look unstyled** — Confirm you have an active
internet connection on the machine running the browser, since Bootstrap 5,
Bootstrap Icons, and SweetAlert2 are loaded from a CDN. For a fully offline
setup, download these libraries and update the `<link>`/`<script>` tags in
`includes/header.php`, `includes/footer.php`, and `login.php` to point to
local copies.
