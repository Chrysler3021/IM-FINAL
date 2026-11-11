# VendFlow — Modern Vending Machine Inventory Management System

> **A Pure PHP SaaS Web Application** for vending machine business operators to digitally track snacks, drinks, monitor low stock thresholds, and organize inventory across multi-machine routes.

---

## ⚠️ Important Product Scope & Hardware Disclaimer

* **Software-Only System**: VendFlow is a **digital inventory recordkeeping software platform**.
* **Physical Hardware Separation**: We are **NOT** selling or manufacturing physical vending machines. The physical vending machine, internal dispensing mechanisms, spiral coils, bill/coin validators, card readers, and internal weight/telemetry sensors are **NOT** part of this product.
* **Manual Digital Tracking**: Unless an external IoT hardware webhook is configured by the operator, stock quantities are updated manually by operators and route restockers using the mobile/tablet/desktop web interface.

---

## 🌟 Key Features

### 1. Public Marketing Website
* **Modern SaaS Landing Page (`index.php`)**:
  * Hero section with headline: *"Take Control of Your Vending Machine Inventory"*
  * Interactive UI Mockup representing the digital software interface with live product cards, machine tags, and stock summaries.
  * 6 Core Benefits: Easy inventory tracking, multiple machine fleet management, rapid add/remove, low-stock threshold monitoring, organized records, and universal device access.
  * 3-Step "How It Works" guide.
  * Pricing preview, FAQs addressing hardware boundaries, and rich SaaS footer.
* **Features Breakdown (`features.php`)**:
  * Detailed breakdown of all implemented capabilities.
  * Clear visual distinction between live implemented features and planned roadmap items (barcode camera scanning, CSV route sheet export, route map optimization).
* **Pricing & Plans (`pricing.php`)**:
  * Starter ($19/mo - 1 machine), Business ($49/mo - up to 15 machines), Enterprise ($119/mo - unlimited fleet).
  * Feature comparison matrix.
* **Purchasing & License Provisioning Prototype (`checkout.php`)**:
  * Integration-ready checkout prototype conforming to Section 6.
  * Generates cryptographically secure digital software license keys (e.g. `BUS-XXXX-XXXX-PROD`) and provisions account limits.

### 2. User Authentication & Isolation
* Functional login (`login.php`) with password hashing (`password_hash` with BCRYPT).
* **1-Click Evaluator Demo Access**: Instant login to the pre-populated demo account without typing credentials.
* New user registration (`register.php`) with plan selection and optional demo data seeding.
* Forgot password flow (`forgot-password.php` & `reset-password.php`).
* Strict multi-tenant user isolation: queries filter strictly by `user_id`. No customer can view or edit another operator's inventory.

### 3. Dedicated Inventory Dashboard (`dashboard.php`)
* **KPI Metric Cards**: Total registered vending machines, unique product SKUs, total units on record, inventory valuation, and active low-stock count.
* **Restock Action List**: Automatically ranks products whose stock is at or below minimum threshold. Includes 1-click "Log Restock" shortcuts.
* **Vending Machine Fleet Summary**: Location tags, stock health breakdown, and machine-level stock counts.
* **Recent Activity Feed**: Real-time log of the latest manual adjustments.

### 4. Vending Machine Management (`machines.php`)
* Create, view, edit, and delete vending machine records.
* Assign friendly identifiers (e.g., `VM-OFFICE-01`), physical room/floor locations, and operational statuses (`active`, `maintenance`, `inactive`).
* Safe deletion with confirmation modal dialogs.

### 5. Inventory and Product Catalog (`inventory.php`)
* Full searchable, filterable catalog with live client-side filtering and server-side safety:
  * Real-time search by Product Name or SKU.
  * Filter by Vending Machine location.
  * Filter by Category (Chips & Snacks, Cold Drinks & Sodas, Candy & Chocolate, Bottled Water & Juices, Healthy Snacks, Pastries & Biscuits, Other).
  * Filter by Stock Status (All, Low Stock Only, Out of Stock, Healthy).
  * Sort by Name (A-Z, Z-A), Stock Quantity (Low-High, High-Low), or Date Updated.
* CRUD operations with modals: Add Product, Edit Product, Delete Product.
* **Adjust Stock Modal**:
  * Restock (+ Add units).
  * Manual Sales Tally (- Deduct units).
  * Damaged / Expired Removal (- Deduct units).
  * Physical Audit Count (Set exact count).
  * Automatic non-negative quantity protection.

### 6. Low-Stock Management (`low-stock.php`)
* Dedicated monitoring screen highlighting stockouts and items nearing depletion.
* Calculates exact units needed to reach threshold.
* Instant 1-click modal restock.

### 7. Audit Trail & History Log (`history.php`)
* Permanent record of all inventory changes: product, vending machine location, previous quantity, new quantity, change amount, change type, operator notes, and timestamp.

### 8. Settings & Profile (`settings.php`)
* Update business name, operator profile, currency symbol (`$`, `€`, `£`, etc.), and default alert thresholds.
* Change password.
* View active software license key and tier.
* **Restore Demo Data**: 1-click button to reset workspace to the Section 9 specification data.

---

## 📦 Sample Data (Conforms to Section 9)

The initial demo account is pre-populated with the exact sample data required in Section 9:

* **Machine A — Office Building** (*2nd Floor Breakroom, Tech Towers*)
  * Potato Chips — 12 units (Min: 10)
  * Chocolate Bar — 8 units (Min: 10) ➔ **LOW STOCK**
  * Bottled Water — 20 units (Min: 15)
  * Cola — 4 units (Min: 12) ➔ **CRITICAL LOW STOCK**
  * Biscuits — 15 units (Min: 10)
* **Machine B — Campus Hall** (*Student Union Center, Building C*)
  * Potato Chips — 6 units (Min: 10) ➔ **LOW STOCK**
  * Chocolate Bar — 3 units (Min: 8) ➔ **LOW STOCK**
  * Bottled Water — 10 units (Min: 12) ➔ **LOW STOCK**
  * Fruit Juice — 7 units (Min: 10) ➔ **LOW STOCK**

---

## 🚀 Quick Start & Installation

### Requirements
* **PHP 8.1+** with `pdo_mysql` enabled (standard in XAMPP, Laragon, WampServer).
* **MySQL 5.7+ / MariaDB 10.4+** (standard in XAMPP on `localhost:3306`).
* **Zero npm, Node.js, or composer build steps required!** Runs completely out of the box.

### Running the Application

1. Ensure **MySQL** is started (e.g. click "Start" next to MySQL in XAMPP Control Panel, or run `run.bat`).
2. Double-click `run.bat` or run the built-in server manually:
   ```bash
   php -S 127.0.0.1:8000
   ```
3. Open your browser and navigate to:
   ```
   http://127.0.0.1:8000/
   ```

### Default Demo Credentials
* **Email**: `demo@vendflow.io`
* **Password**: `password123`
* *(Or simply click the 1-click demo button on `login.php` or the hero banner on `index.php`)*

---

## 🛠️ MySQL Configuration & Environment Variables

VendFlow connects to MySQL via `config/db.php`. Default settings out of the box work immediately with standard local setups (such as XAMPP):

* **Host**: `127.0.0.1` (or `localhost`)
* **Port**: `3306`
* **Database**: `vendflow`
* **User**: `root`
* **Password**: `""` (empty)

You can override these at any time via environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`):

```ini
; Example custom environment variables
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=vendflow
DB_USER=root
DB_PASS=
```

---

## 🧪 Automated Test Suite

A built-in verification script tests the complete end-to-end user flows (registration, login, machine creation, product creation, stock adjustment, product edit, deletion, low-stock detection, and logout):

```bash
php test_flows.php
```

All 22 automated test assertions execute in seconds.

---

## 📁 Project Architecture

```
KAMLON/
├── config/
│   ├── db.php                 # PDO SQLite database connection & auto-migration
│   └── auth.php               # Session security, BCRYPT authentication, CSRF guards
├── includes/
│   ├── header.php             # Public site navigation & header
│   ├── footer.php             # Public site footer & disclaimer
│   ├── dashboard_header.php   # Authenticated dashboard sidebar & topbar
│   ├── dashboard_footer.php   # Dashboard footer & script tags
│   └── modals.php             # Product CRUD, stock adjust & machine modals
├── assets/
│   ├── css/custom.css         # SaaS dashboard styling, typography, animations
│   └── js/app.js              # Live search, dynamic filters, modal handlers
├── index.php                  # Public marketing homepage with hero & UI mockup
├── features.php               # Detailed features breakdown (live vs roadmap)
├── pricing.php                # Configurable pricing plans & comparison matrix
├── checkout.php               # Software licensing checkout prototype
├── login.php                  # User authentication with 1-click demo access
├── register.php               # User registration & workspace setup
├── forgot-password.php        # Password recovery prototype
├── reset-password.php         # Password reset handler
├── logout.php                 # Session termination
├── dashboard.php              # SaaS overview dashboard & restock queues
├── machines.php               # Vending machine fleet management
├── inventory.php              # Comprehensive inventory table & adjustments
├── low-stock.php              # Dedicated low-stock restock center
├── history.php                # Activity audit trail of stock adjustments
├── settings.php               # Business preferences, license & data management
├── api.php                    # JSON API for AJAX stock updates
├── seed.php                   # Database seeder utility
├── test_flows.php             # Automated test suite (22 test assertions)
└── README.md                  # System documentation
```
