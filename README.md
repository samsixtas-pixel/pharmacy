# MPANGO Inventory Management System

A complete, framework-free PHP Object-Oriented pharmacy inventory and sales
management system built with PHP, MySQL (PDO), HTML5, CSS3, Bootstrap 5, and
JavaScript. Built as a university PHP OOP and database assignment.

## Features

- Secure authentication with session protection and bcrypt password hashing
- Public self-registration page (`register.php`) — new accounts are always
  created with Staff access; Admin accounts can only be created by an
  existing Admin via Users management
- Role-based access control: Admin and Staff
- Dashboard with live statistics: total products, boxes, stock, sales, payment
  status breakdown, outstanding balance, and low-stock alerts
- Product (medicine) management: add, edit, delete, view, search
- Independent Box module with its own Box In / Box Out stock movements
- Medicine Stock In / Stock Out with automatic stock level updates
- Sales recording with automatic total calculation and payment-status logic
  (Paid / Partial / Unpaid)
- Debt management: record partial payments against a sale; balance and status
  update automatically, including auto-transition to "Paid" at zero balance
- Eight report types (Product, Box, Box In, Box Out, Stock In, Stock Out,
  Sales, Debt) each supporting search, date filtering, and printing
- Expenses (Matumizi) module: record operating costs by category (rent,
  utilities, salaries, transport, maintenance, supplies, other), with
  dashboard totals (all-time and this-month), search/filter, and a
  dedicated Expense Report
- User management (Admin only): add, edit, deactivate accounts
- Responsive Bootstrap 5 admin layout with sidebar navigation and SweetAlert2
  confirmation dialogs

## Object-Oriented Design

The system demonstrates the five pillars of OOP plus supporting concepts
required for the assignment:

| Concept | Where it appears |
|---|---|
| Encapsulation | Private/protected properties in `User`, `Database`; getters/setters kept internal to each class |
| Abstraction | `abstract class Model`, `abstract class Transaction` define contracts (`getTable()`, `record()`, `applyEffect()`) that child classes must implement |
| Inheritance | `Product`, `Box`, `User`, `Sale`, `Report` all extend `Model`; `StockIn`, `StockOut`, `BoxIn`, `BoxOut` all extend `Transaction` |
| Polymorphism | The `Stockable` interface is implemented differently by `Product` and `Box`; `Transaction::applyEffect()` increases stock in `StockIn`/`BoxIn` but decreases it in `StockOut`/`BoxOut` |
| Constructors | `Model::__construct()` initializes the shared PDO connection for every child class |

### Class Map

```
config/
  Database.php       Singleton PDO connection (secure, prepared statements only)
  config.php          App constants, session hardening

classes/
  Model.php            abstract base: find(), all(), delete(), lastInsertId()
  Stockable.php         interface: increaseStock(), decreaseStock()
  Transaction.php       abstract base for *In/*Out classes: record(), applyEffect(), history()
  User.php              auth, session, CRUD (extends Model)
  Product.php           medicine CRUD, stock helpers (extends Model, implements Stockable)
  Box.php                box CRUD, quantity helpers (extends Model, implements Stockable)
  StockIn.php / StockOut.php   medicine stock movements (extend Transaction)
  BoxIn.php / BoxOut.php       box quantity movements (extend Transaction)
  Sale.php               sales, payment-status logic, debt payments (extends Model)
  Expense.php             expense CRUD, category totals, search/filter (extends Model)
  Report.php              all report queries (extends Model)
  Validator.php           reusable form validation + XSS-safe output helper

includes/
  autoload.php         class autoloader + bootstraps config/Database
  functions.php        formatMoney(), formatDate(), statusBadge(), e()
  header.php / footer.php   shared Bootstrap 5 layout (sidebar, topbar)

views/
  products/ boxes/ stock/ sales/ debts/ reports/ users/   one folder per module
```

## Database Design

The schema (`database.sql`) is normalized to Third Normal Form: every table
has a single-column primary key, no repeating groups, and no column that
depends on anything other than the whole primary key. Ten tables: `users`,
`products`, `boxes`, `box_in`, `box_out`, `stock_in`, `stock_out`, `sales`,
`payments`, `expenses` — see `docs/er_diagram.svg` for the full
entity-relationship diagram and `docs/use_case_diagram.svg` for the
actor/use-case breakdown.

## Sample Login Credentials

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Staff | `staff` | `staff123` |

Change these immediately if deploying anywhere beyond local grading/testing.

## Tech Stack

PHP 8+ (OOP, PDO), MySQL/MariaDB, HTML5, CSS3, Bootstrap 5, vanilla
JavaScript, SweetAlert2, Bootstrap Icons. No PHP framework is used anywhere
in this project, per the assignment requirement.

## Security Notes

- All database queries use PDO prepared statements (`PDO::ATTR_EMULATE_PREPARES`
  disabled) — no raw string interpolation into SQL anywhere.
- Passwords are hashed with `password_hash()` / verified with `password_verify()`.
- All user-supplied output is escaped through the `e()` helper
  (`htmlspecialchars`) before being echoed into HTML, preventing XSS.
- Session cookies are HTTP-only and session fixation is mitigated via
  `session.use_strict_mode`.
- Every protected page calls `User::requireLogin()` or `User::requireAdmin()`
  before rendering.

See `INSTALLATION.md` for setup instructions.
