# Sales Velocity & Smart Dynamic Par Advisor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an automated Sales Velocity Engine and Smart Dynamic Par Advisor (Option 1 + Option 2) across operator and staff portals to detect high-turnover vending inventory, recommend restock cadences (1–2 days, weekly, bi-weekly), flag under-buffered thresholds, and enable 1-click par optimizations.

**Architecture:** Aggregates historical sales transactions from `inventory_logs` (entries with `change_type = 'manual_sale'`) over a rolling 14-day window per product SKU to compute daily burn rate ($V_{\text{daily}}$). A deterministic advisor classifies products into velocity bands (`high`, `steady`, `slow`, `new`), flags under-buffered high-velocity items, and surfaces actionable badges and 1-click threshold update controls across `low-stock.php`, `inventory.php`, and `staff-dashboard.php`.

**Tech Stack:** PHP 8.2+, MySQL (PDO with prepared statements & transactions), PostHog Design System CSS, Vanilla JS.

**Spec:** Option 1 (Fast-Mover & Cadence Badges) + Option 2 (Smart Dynamic Par Advisor) as approved in session design.

## Global Constraints

- Must maintain 100% backward compatibility with existing MySQL schema without schema migrations or table alters.
- All database operations must use PDO prepared statements with transactional integrity where mutations occur.
- UI styling must strictly conform to PostHog design system tokens (colors, buttons, badges, tables, typography) in `assets/css/dashboard.css`.
- Operator forms and POST mutations must be protected by CSRF verification (`verify_csrf($_POST['csrf_token'])`).
- Existing test suites (`test_flows.php`, `test_staff_flows.php`) must continue passing at 100% (31/31 and 25/25 respectively).
- No new external Composer or npm dependencies.

## Review Focus

1. Zero sales logs for a product: Product is brand new or has no `manual_sale` logs; velocity calculation must gracefully return `0.0` with band `'new'`, restock cadence `'Baseline pending'`, and recommended par matching current par without division-by-zero or SQL warnings.
2. Short duration since creation: Product was created 1 day ago; elapsed days calculation $\max(1, \text{days})$ must not produce zero division and must normalize sales correctly.
3. Out-of-bounds par update input: Operator attempts to submit negative, zero, or non-numeric par thresholds; input validation must constrain $\ge 1$ and reject invalid values with a clear flash notice.
4. Cross-tenant isolation on par updates: Operator attempts to update par threshold for a product belonging to another user or machine; query must scope strictly by `user_id = :uid` and reject unauthorized modifications.
5. Large log volume performance: Thousands of inventory logs; velocity calculation must use indexed aggregates on `(user_id, change_type, created_at)` and product group filtering rather than loading all raw rows into PHP memory.

---

### Task 1: Backend Velocity Calculation Engine & Test Suite

**Files:**
- Create: `test_velocity.php`
- Modify: `includes/functions.php`
- Modify: `assets/css/dashboard.css`

**Interfaces:**
- Consumes: `inventory_logs` table (`user_id`, `product_id`, `change_type`, `change_qty`, `created_at`), `products` table (`id`, `user_id`, `quantity`, `min_threshold`).
- Produces:
  - `get_product_velocity_metrics(PDO $db, int $productId, int $daysWindow = 14): array`
    Returns: `['product_id' => int, 'daily_velocity' => float, 'units_sold_window' => int, 'days_observed' => int, 'band' => string, 'cadence_label' => string, 'recommended_par' => int, 'is_under_buffered' => bool, 'buffer_deficit' => int]`
  - `get_workspace_velocity_recommendations(PDO $db, int $workspaceOwnerId, int $daysWindow = 14): array`
    Returns array keyed by `product_id` containing the velocity metrics.
  - `update_product_par_threshold(PDO $db, int $workspaceOwnerId, int $productId, int $newParThreshold): bool`
    Updates `products.min_threshold` atomically and logs audit entry.
  - `get_velocity_badge_info(array $velocityMetrics): array`
    Returns: `['label' => string, 'class' => string, 'icon_name' => string, 'cadence' => string]`

- [ ] **Step 1: Write the failing test in `test_velocity.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
echo "========================================================\n";
echo "VendFlow - Sales Velocity & Par Advisor Test Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertVelTest(string $description, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failCount++;
    }
}

// 1. Setup Test Operator & Products
$pdo->exec("DELETE FROM users WHERE email = 'velocity_test@vendflow.io'");
$opHash = password_hash('secret123', PASSWORD_BCRYPT);
$opStmt = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key)
    VALUES ('Velocity Tester', 'velocity_test@vendflow.io', :hash, 'Fast Snack Ops', 'pro', 'TEST-LIC-VEL-01')
");
$opStmt->execute([':hash' => $opHash]);
$testUid = (int)$pdo->lastInsertId();

$mStmt = $pdo->prepare("INSERT INTO machines (user_id, name, location, status) VALUES (:uid, 'Vel Machine 1', 'Lobby A', 'active')");
$mStmt->execute([':uid' => $testUid]);
$testMid = (int)$pdo->lastInsertId();

// Fast Mover (Selling 10 units/day, current par 8 -> under-buffered!)
$p1 = $pdo->prepare("INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold) VALUES (:uid, :mid, 'Nitro Cold Brew', 'NCB-01', 'Cold Drinks', 4, 8)");
$p1->execute([':uid' => $testUid, ':mid' => $testMid]);
$prodFastId = (int)$pdo->lastInsertId();

// Steady Mover (Selling 2 units/day, par 15 -> adequately buffered)
$p2 = $pdo->prepare("INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold) VALUES (:uid, :mid, 'Granola Bar', 'GB-02', 'Chips & Snacks', 12, 15)");
$p2->execute([':uid' => $testUid, ':mid' => $testMid]);
$prodSteadyId = (int)$pdo->lastInsertId();

// New SKU (0 sales)
$p3 = $pdo->prepare("INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold) VALUES (:uid, :mid, 'New Matcha Can', 'MC-03', 'Cold Drinks', 20, 10)");
$p3->execute([':uid' => $testUid, ':mid' => $testMid]);
$prodNewId = (int)$pdo->lastInsertId();

// Insert simulated sales for Fast Mover: 70 units over last 7 days = 10 units/day
$logIns = $pdo->prepare("
    INSERT INTO inventory_logs (user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes, created_at)
    VALUES (:uid, :pid, :mid, 50, 40, :sold, 'manual_sale', 'Tally sale', DATE_SUB(NOW(), INTERVAL :days DAY))
");
for ($d = 1; $d <= 7; $d++) {
    $logIns->execute([':uid' => $testUid, ':pid' => $prodFastId, ':mid' => $testMid, ':sold' => 10, ':days' => $d]);
}

// Insert simulated sales for Steady Mover: 14 units over 7 days = 2 units/day
for ($d = 1; $d <= 7; $d++) {
    $logIns->execute([':uid' => $testUid, ':pid' => $prodSteadyId, ':mid' => $testMid, ':sold' => 2, ':days' => $d]);
}

// Tests
$fastMetrics = get_product_velocity_metrics($pdo, $prodFastId, 14);
assertVelTest("Fast mover daily velocity is approx 5.0 - 10.0 units/day", $fastMetrics['daily_velocity'] >= 5.0);
assertVelTest("Fast mover band is 'high'", $fastMetrics['band'] === 'high');
assertVelTest("Fast mover is detected as under-buffered", $fastMetrics['is_under_buffered'] === true);
assertVelTest("Fast mover recommended par is higher than current (8)", $fastMetrics['recommended_par'] > 8);

$steadyMetrics = get_product_velocity_metrics($pdo, $prodSteadyId, 14);
assertVelTest("Steady mover band is 'steady'", $steadyMetrics['band'] === 'steady');
assertVelTest("Steady mover is NOT under-buffered", $steadyMetrics['is_under_buffered'] === false);

$newMetrics = get_product_velocity_metrics($pdo, $prodNewId, 14);
assertVelTest("New product with 0 sales has band 'new'", $newMetrics['band'] === 'new');
assertVelTest("New product daily velocity is 0.0", $newMetrics['daily_velocity'] === 0.0);

// Test workspace batch recommendation
$allRecs = get_workspace_velocity_recommendations($pdo, $testUid, 14);
assertVelTest("Workspace recommendations contain all 3 products", count($allRecs) === 3);

// Test atomic par update
$updated = update_product_par_threshold($pdo, $testUid, $prodFastId, $fastMetrics['recommended_par']);
assertVelTest("Par threshold update succeeded", $updated === true);

$checkStmt = $pdo->prepare("SELECT min_threshold FROM products WHERE id = :id");
$checkStmt->execute([':id' => $prodFastId]);
$newPar = (int)$checkStmt->fetchColumn();
assertVelTest("Product min_threshold updated in DB to {$fastMetrics['recommended_par']}", $newPar === $fastMetrics['recommended_par']);

// Cross-tenant protection test
$unauthorized = update_product_par_threshold($pdo, 999999, $prodFastId, 50);
assertVelTest("Cross-tenant par update is rejected", $unauthorized === false);

echo "\nSummary: {$passCount} passed, {$failCount} failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: FAIL with "Call to undefined function get_product_velocity_metrics()"

- [ ] **Step 3: Implement calculation and par update engine in `includes/functions.php` and badge CSS in `assets/css/dashboard.css`**

Add functions to `includes/functions.php`:
- `get_product_velocity_metrics(PDO $db, int $productId, int $daysWindow = 14): array`
  1. Fetch product row (`min_threshold`, `created_at`).
  2. Query `inventory_logs` for `product_id = :pid AND change_type = 'manual_sale' AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)`.
  3. Aggregate `COALESCE(SUM(change_qty), 0) AS total_sold`, `MIN(created_at) AS earliest_sale`.
  4. Calculate observed days: $\max(1, \min(\text{daysWindow}, \text{days\_since\_earliest\_sale}))$.
  5. Compute $V_{\text{daily}} = \text{round}(\text{total\_sold} / \text{observed\_days}, 1)$.
  6. Band classification:
     - If total_sold == 0 &rarr; band `'new'`, cadence `'Baseline pending'`.
     - Else if $V_{\text{daily}} \ge 5.0$ &rarr; band `'high'`, cadence `'Restock every 1–2 days'`.
     - Else if $V_{\text{daily}} \ge 1.0$ &rarr; band `'steady'`, cadence `'Restock weekly'`.
     - Else &rarr; band `'slow'`, cadence `'Restock bi-weekly / as needed'`.
  7. Recommended Par calculation:
     - For `high`: $\text{ceil}(V_{\text{daily}} \times 2) + 2$ (2 days coverage + 2 buffer units).
     - For `steady`: $\text{ceil}(V_{\text{daily}} \times 7)$ (weekly cycle coverage).
     - For `slow` / `new`: $\max(5, \text{current\_par})$.
  8. Under-buffered flag: true if $V_{\text{daily}} \ge 5.0$ and current `min_threshold < recommended_par`.
- `get_workspace_velocity_recommendations(PDO $db, int $workspaceOwnerId, int $daysWindow = 14): array`
  Execute grouped query on `inventory_logs` joined with `products` where `products.user_id = :uid` to compute metrics for all workspace products in a single database roundtrip.
- `update_product_par_threshold(PDO $db, int $workspaceOwnerId, int $productId, int $newParThreshold): bool`
  Atomically update `products.min_threshold = :par WHERE id = :id AND user_id = :uid`, and log entry in `inventory_logs` with `change_type = 'adjustment'`, notes `'Par threshold updated by Par Advisor'`.
- `get_velocity_badge_info(array $velocityMetrics): array`
  Return display metadata (badge class `badge-velocity-high`, `badge-velocity-steady`, `badge-velocity-slow`, `badge-velocity-new`, label, and PostHog icon).
- Add PostHog CSS utility classes for velocity tags (`badge-velocity-high`, `badge-velocity-steady`, etc.) to `assets/css/dashboard.css`.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: `[PASS]` on all 11 assertions, exit code 0.

- [ ] **Step 5: Commit**

```bash
git add test_velocity.php includes/functions.php assets/css/dashboard.css
git commit -m "feat: implement sales velocity engine and par recommendation helpers"
```

---

### Task 2: Par Advisor POST Handler & Mutation Endpoint

**Files:**
- Modify: `low-stock.php:20-50`
- Modify: `inventory.php:200-248`
- Test: `test_velocity.php`

**Interfaces:**
- Consumes: `update_product_par_threshold()`, `verify_csrf()`
- Produces: POST action `apply_par_recommendation` in `low-stock.php` and `inventory.php` handling:
  - `product_id`: int
  - `recommended_par`: int
  - `csrf_token`: string
  - Redirects back with flash success `Par threshold for '[Name]' updated to [X] units.`

- [ ] **Step 1: Write tests in `test_velocity.php` for Par Advisor mutation handling**

Add assertions verifying:
1. Valid par update with matching user ID succeeds and sets exact threshold.
2. Par update with invalid / non-positive par value is constrained to $\ge 1$.
3. Audit log contains the par update entry with previous and new par details.

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: Assertion on audit log entry fails before implementation.

- [ ] **Step 3: Implement POST action in `low-stock.php` and `inventory.php`**

In both files:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ...
    if ($action === 'apply_par_recommendation') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        $newPar = max(1, (int)($_POST['recommended_par'] ?? 0));
        
        if ($prodId > 0 && $newPar > 0) {
            $ok = update_product_par_threshold($db, $userId, $prodId, $newPar);
            if ($ok) {
                set_flash('success', "Updated par capacity to {$newPar} units per Par Advisor recommendation.");
            } else {
                set_flash('error', "Could not update par threshold for the selected product.");
            }
        }
        redirect($returnPage);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: All tests PASS.

- [ ] **Step 5: Commit**

```bash
git add low-stock.php inventory.php test_velocity.php
git commit -m "feat: handle apply_par_recommendation POST mutations with CSRF validation"
```

---

### Task 3: Operator Low Stock Alerts Integration (`low-stock.php`)

**Files:**
- Modify: `low-stock.php`
- Test: `test_velocity.php`

**Interfaces:**
- Consumes: `get_workspace_velocity_recommendations($db, $userId)`, `get_velocity_badge_info($vel)`
- Produces:
  1. **Smart Par Advisor Banner:** Placed right above the restock dispatch table. If any under-buffered fast movers exist, displays an alert banner explaining:
     > `🔥 Smart Par Alert: [N] fast-moving items are under-buffered. Their current par threshold triggers low-stock alerts too late for high daily turnover.`
     Includes product pills with current par vs recommended par and a 1-click button: `Apply Recommended Par (X)`.
  2. **Table Velocity & Cadence Column / Tag:** In each row of the Restock Dispatch Queue, displays the velocity pill:
     - `🔥 High Velocity • Restock 1–2 days (X sold/day)`
     - `⚡ Steady Mover • Weekly (X sold/day)`
     - `🐢 Slow Mover • Bi-weekly`
     - `✨ New SKU • Baseline pending`

- [ ] **Step 1: Write integration assertions in `test_velocity.php` for `low-stock.php` rendering**

Append tests in `test_velocity.php` simulating session for `$testUid` and verifying that:
1. `get_workspace_velocity_recommendations` correctly identifies the under-buffered product.
2. Rendering `low-stock.php` output contains the strings `'Smart Par Advisor'`, `'badge-velocity-high'`, and `'Restock every 1–2 days'`.

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: Fails because markup not yet rendered in `low-stock.php`.

- [ ] **Step 3: Implement Smart Par Advisor banner & velocity column in `low-stock.php`**

1. In PHP preamble of `low-stock.php`:
   - Call `$velocityMap = get_workspace_velocity_recommendations($db, $userId);`
   - Filter `$underBufferedItems = array_filter($velocityMap, fn($v) => !empty($v['is_under_buffered']));`
2. Add Smart Par Advisor banner right before the dispatch table:
   - When `$underBufferedItems` is non-empty, render a PostHog-styled advisory callout card with amber/fire highlight, detailing the recommendation and displaying a mini-form with 1-click update button.
3. In `lowstock-table`:
   - Add column header: `Velocity & Cadence`.
   - In each product row, render `$vInfo = get_velocity_badge_info($velocityMap[$item['id']] ?? null);` with formatted badge and daily sales burn rate.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add low-stock.php test_velocity.php
git commit -m "feat: add Smart Par Advisor alert banner and velocity cadence tags to low-stock.php"
```

---

### Task 4: Operator Products & Catalog Integration (`inventory.php`)

**Files:**
- Modify: `inventory.php`
- Test: `test_velocity.php`

**Interfaces:**
- Consumes: `get_workspace_velocity_recommendations($db, $userId)`, `get_velocity_badge_info()`
- Produces:
  1. Velocity badge chip rendered on every product card in Grid View.
  2. Velocity indicator tag rendered in Table View.
  3. Dynamic Par Recommendation callout inside the `edit-product-modal`:
     - Displays `Smart Par Recommendation: [X] units (burn rate: [Y] sold/day)` with a `Use Recommended Par` button that updates the modal's `min_threshold` input value instantly.

- [ ] **Step 1: Write integration assertions in `test_velocity.php` for `inventory.php`**

Verify that:
1. Product payload in `inventory.php` includes `daily_velocity`, `band`, and `recommended_par`.
2. Output HTML contains velocity badge markup and edit modal smart recommendation block.

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: Fails on missing modal recommendation element.

- [ ] **Step 3: Implement velocity badges & modal advisor in `inventory.php`**

1. In preamble: `$velocityMap = get_workspace_velocity_recommendations($db, $userId);`
2. In Grid card loop: Render velocity pill below SKU row:
   ```html
   <div class="card-velocity-row">
       <span class="badge-pill <?= e($vInfo['class']) ?>"><?= $vInfo['label'] ?> &bull; <?= $vInfo['cadence'] ?></span>
   </div>
   ```
3. In Table view loop: Add velocity tag in the Par / Threshold column.
4. In `edit-product-modal`:
   Add a smart advisor helper block below the Minimum Par Threshold input:
   ```html
   <div id="modal-par-advisor-box" class="par-advisor-box" style="display: none;">
       <div class="par-advisor-text">
           <span>⚡ Par Advisor: </span><span id="modal-par-advisor-text"></span>
       </div>
       <button type="button" class="btn-advisor-apply" onclick="applyModalParSuggestion()">Use suggested par</button>
   </div>
   ```
5. Update `openEditProductModal` JS function to populate `modal-par-advisor-box` dynamically.

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add inventory.php test_velocity.php
git commit -m "feat: integrate velocity tags and dynamic modal par advisor into inventory.php"
```

---

### Task 5: Staff Field Restock Workspace Integration (`staff-dashboard.php`)

**Files:**
- Modify: `staff-dashboard.php`
- Test: `test_velocity.php`

**Interfaces:**
- Consumes: `get_workspace_velocity_recommendations($db, $operatorId)`
- Produces:
  1. Fast-mover & cadence indicators on the machine slot inventory table in `staff-dashboard.php`.
  2. Field restockers servicing routes see which items burn fastest and require frequent restocking (e.g. `🔥 Fast Mover • Restock every 1–2 days`), helping them prioritize cart packing and machine loading.

- [ ] **Step 1: Write integration assertions in `test_velocity.php` for `staff-dashboard.php`**

Verify that:
1. `staff-dashboard.php` queries or derives velocity recommendations for the route's products.
2. HTML output contains the restock cadence badge for high-velocity items.

- [ ] **Step 2: Run test to verify it fails**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: Fails on missing cadence indicator in staff dashboard table.

- [ ] **Step 3: Implement velocity indicators in `staff-dashboard.php`**

1. In `staff-dashboard.php` preamble:
   - When `$db` is available, load `$velocityMap = get_workspace_velocity_recommendations($db, $uid);`
2. In table row loop:
   - In the Product Description cell or Coil/SKU cell, add a compact restock cadence pill:
   ```html
   <?php if (!empty($velocityMap[$item['id']]['band']) && $velocityMap[$item['id']]['band'] === 'high'): ?>
       <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 mt-1">
           🔥 Fast Mover (Restock 1–2d)
       </span>
   <?php endif; ?>
   ```

- [ ] **Step 4: Run test to verify it passes**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add staff-dashboard.php test_velocity.php
git commit -m "feat: display fast-mover restock cadence indicators in staff-dashboard.php"
```

---

### Task 6: Full System Test Suite & Regression Verification

**Files:**
- Test: `test_velocity.php`
- Test: `test_flows.php`
- Test: `test_staff_flows.php`

**Interfaces:**
- Consumes: All completed tasks and existing verification suites.
- Produces: End-to-end evidence verifying zero regressions across operator and staff workflows.

- [ ] **Step 1: Run velocity test suite**

Run: `C:\xampp\php\php.exe test_velocity.php`
Expected: All tests pass (0 failures).

- [ ] **Step 2: Run existing platform test suite**

Run: `C:\xampp\php\php.exe test_flows.php`
Expected: 31/31 pass (0 failures).

- [ ] **Step 3: Run staff workflow test suite**

Run: `C:\xampp\php\php.exe test_staff_flows.php`
Expected: 25/25 pass (0 failures).

- [ ] **Step 4: Verify HTTP endpoints on running development server**

Fetch and verify:
- `http://localhost:8000/low-stock.php` (Returns HTTP 200, renders Par Advisor and table)
- `http://localhost:8000/inventory.php` (Returns HTTP 200, renders cards and table)
- `http://localhost:8000/staff-dashboard.php` (Returns HTTP 200, renders restock queue)

- [ ] **Step 5: Commit & wrap up branch**

```bash
git add -A
git commit -m "chore: complete sales velocity par advisor test verification"
```
