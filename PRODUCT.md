# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Independent Vending Machine Business Owners & Operators:** Small-to-midsize fleet operators managing from a single machine up to 15+ locations (offices, campus halls, breakrooms) who need a reliable digital record of stock levels, product valuation, and low-stock priorities without investing in expensive proprietary hardware telemetry.
- **Route Restockers & Field Staff:** Operators working on-site at physical machines from a phone, tablet, or laptop who need to quickly check what a location needs before loading a cart and log restocks, sales tallies, expired/damaged removals, or physical audit counts on the spot.
- **Prospective Buyers & Evaluators:** Visitors exploring the public marketing site, pricing tiers, and 1-click live demo to verify that the platform fits their route operations.

## Product Purpose

VendFlow is a digital vending machine inventory recordkeeping SaaS web application. It replaces paper clipboards and scattered spreadsheets with a centralized, multi-machine inventory system where operators register machines, catalog snack and drink SKUs, set per-product minimum stock thresholds, log quantity adjustments with a full audit trail, and immediately see which machines and items require restocking.

## Positioning

VendFlow is strictly a **software-only digital inventory recordkeeping platform** built for manual and route-based tracking—explicitly separated from physical vending machine manufacturing, internal dispensing coils, bill/coin validators, card readers, or proprietary hardware weight/telemetry sensors. Operators can adopt it immediately across any existing physical fleet with zero hardware retrofits.

## Operating Context

- **Route Planning & Preparation (Desktop / Tablet):** Operators review the dashboard KPI cards, machine health summaries, and the dedicated Low-Stock Management view before heading out on a route to see exact unit deficits against minimum thresholds.
- **On-Site Machine Servicing (Mobile / Tablet / Laptop):** At a physical location (e.g., an office breakroom or student union), the operator filters the catalog by machine, performs a physical count or loads product, and logs the adjustment (`Restock`, `Manual Sales Tally`, `Damaged / Expired Removal`, or `Physical Audit Count`) in seconds.
- **Evaluation & Onboarding:** Prospective operators inspect the live interactive preview on the landing page, compare tiered machine limits (`Starter` $19/mo for 1 machine, `Business` $49/mo for up to 15 machines, `Enterprise` $119/mo for unlimited fleet), provision a software license key via checkout, or enter the pre-seeded workspace via 1-click Demo Access.

## Capabilities and Constraints

- **Zero-Build Pure PHP Stack:** Runs directly on PHP 8.1+/8.2+ (XAMPP / built-in PHP server) with vanilla JavaScript and server-rendered PHP views—no Node.js, npm, or Composer build step required.
- **Database Architecture (SQLite to MySQL Transition):** Currently backed by PDO SQLite (`data/vendflow.sqlite` via `config/db.php` with auto-schema initialization), with a planned migration from SQLite to MySQL while keeping the zero-build Pure PHP PDO architecture.
- **Strict Multi-Tenant Isolation:** Every machine, product SKU, and inventory audit log is scoped strictly by `user_id` so operators can only view or modify their own fleet records.
- **Inventory Integrity:** Stock adjustments enforce non-negative quantities (`>= 0`) and automatically record an immutable entry in `inventory_logs` capturing previous quantity, new quantity, delta, adjustment type, operator notes, and timestamp.
- **Hardware Boundary Disclaimer:** Public and product surfaces must maintain clear disclosure that VendFlow is digital recordkeeping software and does not sell physical vending machines or control internal hardware sensors.
- **Seeded Reference Dataset:** Includes a built-in evaluator demo account (`demo@vendflow.io`) and 1-click reset (`seed.php` / `settings.php`) pre-populated with Machine A (Office Building — 2nd Floor Breakroom, Tech Towers) and Machine B (Campus Hall — Student Union Center, Building C) across 9 snack and beverage SKUs.

## Brand Commitments

- **Name:** VendFlow
- **Voice & Tone:** Practical, transparent, operational, and direct—speaking the language of route operators (SKUs, units, par/minimum thresholds, stockouts, restock queues, audit counts) without overpromising automated hardware magic.

## Evidence on Hand

- **Public Marketing & Conversion Surfaces:** [index.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/index.php), [features.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/features.php), [pricing.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/pricing.php), and [checkout.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/checkout.php) (software license key generator prototype).
- **Authentication & Account Flows:** [login.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/login.php) (with 1-click demo login), [register.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/register.php), [forgot-password.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/forgot-password.php), [reset-password.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/reset-password.php), and [logout.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/logout.php).
- **Operator Application Surfaces:** [dashboard.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/dashboard.php), [machines.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/machines.php), [inventory.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/inventory.php), [low-stock.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/low-stock.php), [history.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/history.php), [settings.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/settings.php), and AJAX endpoint [api.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/api.php).
- **Automated Flow Verification:** [test_flows.php](file:///c:/Users/User/Desktop/KAMLON/KAMLON/test_flows.php) covering end-to-end registration, auth, machine/product CRUD, stock adjustments, and low-stock detection.
- **Absences Not to Fabricate:** No real third-party customer logos, external operator testimonials, press mentions, or physical IoT hardware telemetry benchmarks exist in the repository; future work must not invent fake social proof or claim automated sensor integration as a live feature.

## Product Principles

1. **Actionable Restock Clarity First:** Every operational view should immediately answer which machines have low or depleted items and how many units are needed to restore threshold health.
2. **Fast Field Logging Without Friction:** Adjusting stock quantities—whether adding restock units, deducting sales, removing damaged goods, or setting an audit count—must take minimal taps and always leave a clear audit trail.
3. **Honest Software Boundaries:** Always represent VendFlow accurately as a digital recordkeeping system rather than physical vending hardware or automated telemetry.
4. **Zero-Build Operational Simplicity:** Preserve a clean, self-contained PHP + PDO (SQLite transitioning to MySQL) architecture that runs reliably on standard local and production PHP environments without complex build pipelines.
