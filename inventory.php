<?php
/**
 * VendFlow - Products & Stock Visual Catalog + MySQL Ledger CRUD
 * Pure Vanilla PHP + PDO Prepared Statements + Multipart Image Upload
 */
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();
$db = get_db();
$userId = get_workspace_owner_id($user);
$currency = $user['currency'] ?: '$';

$allowedReturnPages = ['dashboard.php', 'inventory.php', 'machines.php', 'low-stock.php', 'audit-log.php', 'settings.php'];
function resolve_inventory_return(array $allowed): string {
    $ret = basename($_POST['return_to'] ?? 'inventory.php');
    return in_array($ret, $allowed, true) ? $ret : 'inventory.php';
}

// -----------------------------------------------------------------------------
// Handle POST Mutations (Add Product, Edit Product, Adjust Stock, Delete SKU)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnPage = resolve_inventory_return($allowedReturnPages);

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Your session security token expired. Please submit the form again.');
        redirect($returnPage);
    }

    $action = $_POST['action'] ?? '';

    // 1. Add New Product (with Multipart Image Upload)
    if ($action === 'add_product') {
        $machineId    = (int)($_POST['machine_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $skuInput     = trim($_POST['sku'] ?? '');
        $category     = trim($_POST['category'] ?? 'Chips & Snacks');
        $quantity     = max(0, (int)($_POST['quantity'] ?? 0));
        $minThreshold = max(1, (int)($_POST['min_threshold'] ?? ($user['default_threshold'] ?? 10)));
        $costPrice    = max(0, (float)($_POST['cost_price'] ?? 0));
        $sellPrice    = max(0, (float)($_POST['sell_price'] ?? 0));
        $notes        = trim($_POST['notes'] ?? '');

        // Verify machine belongs to signed-in operator
        $mCheck = $db->prepare("SELECT id, name FROM machines WHERE id = :mid AND user_id = :uid");
        $mCheck->execute([':mid' => $machineId, ':uid' => $userId]);
        $machineRow = $mCheck->fetch();

        if (!$machineRow) {
            set_flash('error', 'Select a valid registered vending machine before adding a product.');
            redirect('inventory.php');
        }

        if ($name === '') {
            set_flash('error', 'Product name is required.');
            redirect('inventory.php');
        }

        // Auto-generate SKU if left blank
        if ($skuInput === '') {
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category), 0, 3) ?: 'SKU');
            $sku = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        } else {
            $sku = strtoupper($skuInput);
        }

        try {
            $imageUrl = handle_product_image_upload('image', $category, null);

            $ins = $db->prepare("
                INSERT INTO products (
                    user_id, machine_id, name, sku, category,
                    quantity, min_threshold, cost_price, sell_price, image_url, notes
                ) VALUES (
                    :uid, :mid, :name, :sku, :cat,
                    :qty, :min_t, :cost, :sell, :img, :notes
                )
            ");
            $ins->execute([
                ':uid'   => $userId,
                ':mid'   => $machineId,
                ':name'  => $name,
                ':sku'   => $sku,
                ':cat'   => $category,
                ':qty'   => $quantity,
                ':min_t' => $minThreshold,
                ':cost'  => $costPrice,
                ':sell'  => $sellPrice,
                ':img'   => $imageUrl,
                ':notes' => $notes
            ]);
            $newProdId = (int)$db->lastInsertId();

            // Record initial stocking entry in audit log
            $logStmt = $db->prepare("
                INSERT INTO inventory_logs (
                    user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes
                ) VALUES (
                    :uid, :pid, :mid, 0, :nqty, :cqty, 'initial', :notes
                )
            ");
            $logStmt->execute([
                ':uid'   => $userId,
                ':pid'   => $newProdId,
                ':mid'   => $machineId,
                ':nqty'  => $quantity,
                ':cqty'  => $quantity,
                ':notes' => $notes !== '' ? $notes : 'Initial product stocking record'
            ]);

            set_flash('success', "Added '{$name}' ({$quantity} units) to {$machineRow['name']}.");
        } catch (RuntimeException $uploadEx) {
            set_flash('error', $uploadEx->getMessage());
        }

        // Always open Products & Stock when adding from inventory, or return to caller
        redirect($returnPage === 'machines.php' ? 'inventory.php?machine=' . $machineId : 'inventory.php');
    }

    // 2. Edit Existing Product Record
    if ($action === 'edit_product') {
        $prodId       = (int)($_POST['product_id'] ?? 0);
        $machineId    = (int)($_POST['machine_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $skuInput     = trim($_POST['sku'] ?? '');
        $category     = trim($_POST['category'] ?? 'Chips & Snacks');
        $quantity     = max(0, (int)($_POST['quantity'] ?? 0));
        $minThreshold = max(1, (int)($_POST['min_threshold'] ?? 10));
        $costPrice    = max(0, (float)($_POST['cost_price'] ?? 0));
        $sellPrice    = max(0, (float)($_POST['sell_price'] ?? 0));
        $notes        = trim($_POST['notes'] ?? '');

        $pStmt = $db->prepare("SELECT * FROM products WHERE id = :pid AND user_id = :uid");
        $pStmt->execute([':pid' => $prodId, ':uid' => $userId]);
        $existingProd = $pStmt->fetch();

        $mCheck = $db->prepare("SELECT id FROM machines WHERE id = :mid AND user_id = :uid");
        $mCheck->execute([':mid' => $machineId, ':uid' => $userId]);

        if (!$existingProd || !$mCheck->fetch()) {
            set_flash('error', 'Product or assigned machine record was not found.');
            redirect($returnPage);
        }

        // Restrict price changes if staff member lacks price editing permission
        if (!can_manage_prices($user)) {
            $costPrice = (float)$existingProd['cost_price'];
            $sellPrice = (float)$existingProd['sell_price'];
        }

        if ($name === '') {
            set_flash('error', 'Product name cannot be blank.');
            redirect($returnPage);
        }

        $sku = $skuInput !== '' ? strtoupper($skuInput) : $existingProd['sku'];

        try {
            $imageUrl = handle_product_image_upload('image', $category, $existingProd['image_url']);

            $upd = $db->prepare("
                UPDATE products
                SET machine_id = :mid,
                    name = :name,
                    sku = :sku,
                    category = :cat,
                    quantity = :qty,
                    min_threshold = :min_t,
                    cost_price = :cost,
                    sell_price = :sell,
                    image_url = :img,
                    notes = :notes
                WHERE id = :pid AND user_id = :uid
            ");
            $upd->execute([
                ':mid'   => $machineId,
                ':name'  => $name,
                ':sku'   => $sku,
                ':cat'   => $category,
                ':qty'   => $quantity,
                ':min_t' => $minThreshold,
                ':cost'  => $costPrice,
                ':sell'  => $sellPrice,
                ':img'   => $imageUrl,
                ':notes' => $notes,
                ':pid'   => $prodId,
                ':uid'   => $userId
            ]);

            $prevQty = (int)$existingProd['quantity'];
            if ($prevQty !== $quantity) {
                $delta = $quantity - $prevQty;
                $logStmt = $db->prepare("
                    INSERT INTO inventory_logs (
                        user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes
                    ) VALUES (
                        :uid, :pid, :mid, :pqty, :nqty, :cqty, 'adjustment', 'Quantity updated via product record editor'
                    )
                ");
                $logStmt->execute([
                    ':uid'  => $userId,
                    ':pid'  => $prodId,
                    ':mid'  => $machineId,
                    ':pqty' => $prevQty,
                    ':nqty' => $quantity,
                    ':cqty' => $delta
                ]);
            }

            set_flash('success', "Updated product record '{$name}'.");
        } catch (RuntimeException $uploadEx) {
            set_flash('error', $uploadEx->getMessage());
        }

        redirect($returnPage);
    }

    // 3. Log Manual Stock Adjustment (Restock, Sales Tally, Spoilage, Audit)
    if ($action === 'adjust_stock') {
        $prodId     = (int)($_POST['product_id'] ?? 0);
        $changeType = $_POST['change_type'] ?? 'restock';
        $amount     = max(0, (int)($_POST['amount'] ?? 0));
        $notes      = trim($_POST['notes'] ?? '');

        try {
            $res = apply_stock_adjustment($db, $userId, $prodId, $changeType, $amount, $notes, $user);
            $sign = $res['delta'] >= 0 ? '+' . $res['delta'] : (string)$res['delta'];
            set_flash('success', "Logged stock count for '{$res['product']['name']}' ({$sign} units → {$res['new_qty']} in machine).");
        } catch (RuntimeException $re) {
            set_flash('error', $re->getMessage());
        } catch (Throwable $e) {
            set_flash('error', 'Could not record stock adjustment.');
        }

        redirect($returnPage);
    }

    // 4. Delete Product SKU
    if ($action === 'delete_item' && ($_POST['type'] ?? '') === 'product') {
        $prodId = (int)($_POST['id'] ?? 0);
        $del = $db->prepare("DELETE FROM products WHERE id = :pid AND user_id = :uid");
        $del->execute([':pid' => $prodId, ':uid' => $userId]);

        set_flash('success', 'Product SKU removed from your inventory ledger.');
        redirect($returnPage);
    }

    // 5. Apply Smart Par Recommendation (Par Advisor)
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

// -----------------------------------------------------------------------------
// Query Products & Registered Machines for Display
// -----------------------------------------------------------------------------
$selectedMachineId = isset($_GET['machine']) ? (int)$_GET['machine'] : 0;

$machStmt = $db->prepare("SELECT id, name, location FROM machines WHERE user_id = :uid ORDER BY name ASC");
$machStmt->execute([':uid' => $userId]);
$machinesList = $machStmt->fetchAll();

$prodStmt = $db->prepare("
    SELECT
        p.*,
        m.name AS machine_name,
        m.location AS machine_location
    FROM products p
    JOIN machines m ON p.machine_id = m.id
    WHERE p.user_id = :uid
    ORDER BY p.created_at DESC, p.id DESC
");
$prodStmt->execute([':uid' => $userId]);
$allProducts = $prodStmt->fetchAll();

// Fetch rolling sales velocity metrics & dynamic par recommendations
$velocityMap = get_workspace_velocity_recommendations($db, $userId);

$totalCount  = count($allProducts);
$drinksCount = 0;
$candyCount  = 0;
$snacksCount = 0;
$lowCount    = 0;

foreach ($allProducts as $p) {
    $catKey = get_category_filter_key($p['category']);
    if ($catKey === 'drinks') {
        $drinksCount++;
    } elseif ($catKey === 'candy') {
        $candyCount++;
    } else {
        $snacksCount++;
    }
    if (is_low_stock((int)$p['quantity'], (int)$p['min_threshold'])) {
        $lowCount++;
    }
}

$pageTitle    = 'Products & Stock';
$pageHeader   = 'Products & stock (' . $totalCount . ')';
$pageSubtitle = 'Visual inventory catalog and par ledger across all registered vending cabinets.';

require_once __DIR__ . '/includes/dashboard_header.php';
?>

<?php if ($totalCount === 0): ?>

    <!-- ===================================================================
         EMPTY STATE (Shown when 0 products exist in workspace)
         =================================================================== -->
    <div class="empty-state-box" id="empty-state">
        <div class="empty-icon-wrap" style="width:40px;height:40px;margin:0 auto 0.5rem;color:#94a3b8;"><?= posthog_icon('box') ?></div>
        <h3 class="empty-title">No products in catalog yet</h3>
        <p class="empty-desc">
            Your product and stock ledger is currently empty. Add your snacks, beverages, or specialty items with an image and initial units to start tracking inventory.
        </p>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center;">
            <button type="button" class="btn-dash-primary" style="padding: 0.65rem 1.4rem; font-size: 0.9rem;" onclick="openAddProductModal(<?= $selectedMachineId > 0 ? $selectedMachineId : '' ?>)">
                + Add your first product
            </button>
            <?php if (empty($machinesList)): ?>
                <button type="button" class="btn-dash-outline" style="padding: 0.65rem 1.2rem; font-size: 0.9rem;" onclick="openAddMachineModal()">
                    Register machine first
                </button>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

    <!-- ===================================================================
         Toolbar: Real-time Search + Machine Filter + Grid/Table Switcher
         =================================================================== -->
    <div class="catalog-toolbar" id="catalog-toolbar">
        <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; flex-wrap: wrap;">
            <div class="search-box-wrapper">
                <span class="search-icon-inside" style="width:14px;height:14px;display:inline-flex;align-items:center;color:#94a3b8;"><?= posthog_icon('search') ?></span>
                <input
                    type="text"
                    class="catalog-search-input"
                    placeholder="Search product name, SKU, or machine... (Ctrl+K)"
                    id="catalog-search"
                    oninput="filterProducts()"
                >
            </div>

            <select
                id="catalog-machine-filter"
                class="form-input-text"
                style="width: auto; min-width: 215px; padding: 0.52rem 0.85rem; font-size: 0.84rem;"
                onchange="filterProducts()"
            >
                <option value="">All machines (<?= count($machinesList) ?>)</option>
                <?php foreach ($machinesList as $mItem): ?>
                    <option value="<?= (int)$mItem['id'] ?>" <?= $selectedMachineId === (int)$mItem['id'] ? 'selected' : '' ?>>
                        <?= e($mItem['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="view-switcher-group">
            <button type="button" class="view-btn active" id="btn-view-grid" onclick="switchView('grid')">
                <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;"><?= posthog_icon('dashboard') ?></span> Grid
            </button>
            <button type="button" class="view-btn" id="btn-view-table" onclick="switchView('table')">
                <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;"><?= posthog_icon('document') ?></span> Table
            </button>
        </div>
    </div>

    <!-- Category & Par Status Filter Pills -->
    <div class="filter-pills-container" id="filter-pills">
        <button type="button" class="filter-chip active" onclick="setCategoryFilter('all', this)">
            All items <span class="chip-count"><?= $totalCount ?></span>
        </button>
        <button type="button" class="filter-chip" onclick="setCategoryFilter('drinks', this)">
            <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:4px;"><?= posthog_icon('box') ?></span>Drinks &amp; Juices <span class="chip-count"><?= $drinksCount ?></span>
        </button>
        <button type="button" class="filter-chip" onclick="setCategoryFilter('candy', this)">
            <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:4px;"><?= posthog_icon('box') ?></span>Chocolate &amp; Candy <span class="chip-count"><?= $candyCount ?></span>
        </button>
        <button type="button" class="filter-chip" onclick="setCategoryFilter('snacks', this)">
            <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:4px;"><?= posthog_icon('box') ?></span>Chips &amp; Snacks <span class="chip-count"><?= $snacksCount ?></span>
        </button>
        <button type="button" class="filter-chip chip-warning" onclick="setCategoryFilter('low-stock', this)">
            <span style="width:13px;height:13px;display:inline-flex;vertical-align:-1px;margin-right:4px;color:#d97706;"><?= posthog_icon('warning') ?></span>Needs Restock <span class="chip-count"><?= $lowCount ?></span>
        </button>
    </div>

    <!-- Filter Empty Match Notice -->
    <div class="empty-state-box" id="filter-no-results" style="display: none; padding: 2.5rem 1.5rem;">
        <div style="width:36px;height:36px;margin:0 auto 0.5rem;color:#94a3b8;"><?= posthog_icon('search') ?></div>
        <h3 class="empty-title" style="font-size: 1.05rem;">No matching products</h3>
        <p class="empty-desc" style="margin-bottom: 0;">Try clearing your search query or switching machine/category filters.</p>
    </div>

    <!-- ===================================================================
         VIEW 1: Visual Product Cards Grid
         =================================================================== -->
    <div class="product-card-grid" id="product-grid-view">
        <?php foreach ($allProducts as $p):
            $qty      = (int)$p['quantity'];
            $par      = max(1, (int)$p['min_threshold']);
            $isLow    = is_low_stock($qty, $par);
            $percent  = min(100, (int)round(($qty / $par) * 100));
            $fillCls  = $qty === 0 ? 'fill-critical' : ($isLow ? ($percent <= 40 ? 'fill-critical' : 'fill-warning') : 'fill-nominal');
            $bgClass  = get_category_bg_class($p['category']);
            $catKey   = get_category_filter_key($p['category']);
            $imgSrc   = get_product_image($p);
            $needed   = max(1, $par - $qty);
            $searchBlob = strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['machine_name'] . ' ' . $p['category']);

            $velMetrics = $velocityMap[$p['id']] ?? null;
            $vInfo      = get_velocity_badge_info($velMetrics);

            $jsProductPayload = htmlspecialchars(json_encode([
                'id'              => (int)$p['id'],
                'machine_id'      => (int)$p['machine_id'],
                'machine_name'    => $p['machine_name'],
                'name'            => $p['name'],
                'sku'             => $p['sku'],
                'category'        => $p['category'],
                'quantity'        => $qty,
                'min_threshold'   => $par,
                'cost_price'      => number_format((float)$p['cost_price'], 2, '.', ''),
                'sell_price'      => number_format((float)$p['sell_price'], 2, '.', ''),
                'image_url'       => $imgSrc,
                'notes'           => $p['notes'] ?? '',
                'daily_velocity'  => (float)($velMetrics['daily_velocity'] ?? 0.0),
                'velocity_band'   => $velMetrics['band'] ?? 'new',
                'cadence_label'   => $velMetrics['cadence_label'] ?? '',
                'recommended_par' => (int)($velMetrics['recommended_par'] ?? $par)
            ]), ENT_QUOTES, 'UTF-8');
        ?>
            <div
                class="product-item-card"
                data-search="<?= e($searchBlob) ?>"
                data-category="<?= e($catKey) ?>"
                data-machine="<?= (int)$p['machine_id'] ?>"
                data-low="<?= $isLow ? '1' : '0' ?>"
            >
                <div class="card-media-box <?= e($bgClass) ?>">
                    <span class="card-category-tag"><?= e($p['category']) ?></span>
                    <span class="card-price-badge"><?= format_currency((float)$p['sell_price'], $currency) ?></span>
                    <img src="<?= e($imgSrc) ?>" alt="<?= e($p['name']) ?>" class="product-thumb-svg">
                </div>

                <div class="card-body-content">
                    <div class="card-product-title"><?= e($p['name']) ?></div>
                    <div class="card-product-sku-row">
                        <span>SKU: <strong><?= e($p['sku']) ?></strong></span>
                        <span>Cost <?= format_currency((float)$p['cost_price'], $currency) ?></span>
                    </div>

                    <div class="card-velocity-row" style="margin-top: 0.35rem; display: flex; align-items: center; justify-content: space-between;">
                        <span class="badge-pill <?= e($vInfo['class']) ?>" style="font-size: 0.7rem;">
                            <?= $vInfo['label'] ?>
                        </span>
                        <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">
                            <?= e($vInfo['cadence']) ?>
                        </span>
                    </div>

                    <div class="card-machine-loc" title="<?= e($p['machine_location']) ?>">
                        <span style="width:12px;height:12px;display:inline-flex;color:var(--text-muted);vertical-align:-1px;"><?= posthog_icon('map_pin') ?></span>
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($p['machine_name']) ?></span>
                    </div>

                    <div class="stock-bar-group">
                        <div class="stock-bar-labels">
                            <span class="stock-count-highlight"><?= $qty ?> / <?= $par ?> par</span>
                            <?php if ($qty === 0): ?>
                                <span style="color: #dc2626; font-weight: 800;">Empty (+<?= $needed ?>)</span>
                            <?php elseif ($isLow): ?>
                                <span style="color: #d97706; font-weight: 700;">Below par (+<?= $needed ?>)</span>
                            <?php else: ?>
                                <span style="color: #16a34a; font-weight: 700;">At par</span>
                            <?php endif; ?>
                        </div>
                        <div class="stock-progress-track">
                            <div class="stock-progress-fill <?= $fillCls ?>" style="width: <?= $percent ?>%;"></div>
                        </div>
                    </div>

                    <div class="card-action-bar">
                        <button
                            type="button"
                            class="btn-card-restock <?= $isLow ? 'warning' : '' ?>"
                            onclick="openAdjustStockModal(<?= $jsProductPayload ?>, 'restock')"
                        >
                            <?= $isLow ? '+ Restock' : 'Adjust stock' ?>
                        </button>
                        <button
                            type="button"
                            class="btn-card-adjust"
                            title="Edit Product Details & Image"
                            onclick="openEditProductModal(<?= $jsProductPayload ?>)"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            class="btn-danger-outline"
                            title="Delete Product"
                            onclick="openDeleteModal('product', <?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8') ?>)"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===================================================================
         VIEW 2: Compact Ledger Table View
         =================================================================== -->
    <div class="posthog-table-card" id="product-table-view" style="display: none; margin-bottom: 2.5rem;">
        <div class="posthog-table-responsive">
            <table class="posthog-data-table">
                <thead>
                    <tr>
                        <th>Product &amp; Image</th>
                        <th>SKU</th>
                        <th>Machine Placement</th>
                        <th style="width: 180px;">Units / Par Level</th>
                        <th>Status</th>
                        <th>Vend / Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allProducts as $p):
                        $qty     = (int)$p['quantity'];
                        $par     = max(1, (int)$p['min_threshold']);
                        $isLow   = is_low_stock($qty, $par);
                        $percent = min(100, (int)round(($qty / $par) * 100));
                        $fillCls = $qty === 0 ? 'fill-critical' : ($isLow ? ($percent <= 40 ? 'fill-critical' : 'fill-warning') : 'fill-nominal');
                        $bgClass = get_category_bg_class($p['category']);
                        $catKey  = get_category_filter_key($p['category']);
                        $imgSrc  = get_product_image($p);
                        $searchBlob = strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['machine_name'] . ' ' . $p['category']);

                        $velMetrics = $velocityMap[$p['id']] ?? null;
                        $vInfo      = get_velocity_badge_info($velMetrics);

                        $jsProductPayload = htmlspecialchars(json_encode([
                            'id'              => (int)$p['id'],
                            'machine_id'      => (int)$p['machine_id'],
                            'machine_name'    => $p['machine_name'],
                            'name'            => $p['name'],
                            'sku'             => $p['sku'],
                            'category'        => $p['category'],
                            'quantity'        => $qty,
                            'min_threshold'   => $par,
                            'cost_price'      => number_format((float)$p['cost_price'], 2, '.', ''),
                            'sell_price'      => number_format((float)$p['sell_price'], 2, '.', ''),
                            'image_url'       => $imgSrc,
                            'notes'           => $p['notes'] ?? '',
                            'daily_velocity'  => (float)($velMetrics['daily_velocity'] ?? 0.0),
                            'velocity_band'   => $velMetrics['band'] ?? 'new',
                            'cadence_label'   => $velMetrics['cadence_label'] ?? '',
                            'recommended_par' => (int)($velMetrics['recommended_par'] ?? $par)
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr
                            class="table-row-item"
                            data-search="<?= e($searchBlob) ?>"
                            data-category="<?= e($catKey) ?>"
                            data-machine="<?= (int)$p['machine_id'] ?>"
                            data-low="<?= $isLow ? '1' : '0' ?>"
                        >
                            <td>
                                <div class="product-row-flex">
                                    <div class="table-mini-thumb <?= e($bgClass) ?>">
                                        <img src="<?= e($imgSrc) ?>" alt="" style="max-height: 28px; max-width: 30px; object-fit: contain;">
                                    </div>
                                    <div>
                                        <div class="cell-product-name"><?= e($p['name']) ?></div>
                                        <div class="cell-product-sku"><?= e($p['category']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><code style="font-weight: 700; font-size: 0.78rem;"><?= e($p['sku']) ?></code></td>
                            <td>
                                <div class="cell-machine-name" style="font-size: 0.82rem;"><?= e($p['machine_name']) ?></div>
                                <div class="cell-machine-loc"><?= e($p['machine_location']) ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.78rem; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between;">
                                    <span><?= $qty ?> / <?= $par ?> units</span>
                                    <span class="badge-pill <?= e($vInfo['class']) ?>" style="font-size: 0.68rem;"><?= $vInfo['label'] ?></span>
                                </div>
                                <div class="stock-progress-track">
                                    <div class="stock-progress-fill <?= $fillCls ?>" style="width: <?= $percent ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($qty === 0): ?>
                                    <span class="badge-pill badge-loss-red">Empty (0)</span>
                                <?php elseif ($isLow): ?>
                                    <span class="badge-below-par">Below par</span>
                                <?php else: ?>
                                    <span class="badge-active-green">At par</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?= format_currency((float)$p['sell_price'], $currency) ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-muted);">Cost <?= format_currency((float)$p['cost_price'], $currency) ?></div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    <button type="button" class="btn-log-restock" onclick="openAdjustStockModal(<?= $jsProductPayload ?>, 'restock')">
                                        <?= $isLow ? 'Restock' : 'Adjust' ?>
                                    </button>
                                    <button type="button" class="btn-card-adjust" style="padding: 0.32rem 0.55rem; font-size: 0.75rem;" onclick="openEditProductModal(<?= $jsProductPayload ?>)">
                                        Edit
                                    </button>
                                    <button type="button" class="btn-danger-outline" style="padding: 0.32rem 0.5rem; font-size: 0.75rem;" onclick="openDeleteModal('product', <?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8') ?>)">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<div>
    <a href="dashboard.php" class="back-landing-link">&larr; Back to Inventory Overview</a>
</div>

<script>
// Dynamic Par Advisor handler for Edit Modal in Inventory
function applyModalParSuggestion() {
    if (window.pendingSuggestedPar) {
        const minThreshInput = document.getElementById('product-min-threshold');
        if (minThreshInput) {
            minThreshInput.value = window.pendingSuggestedPar;
        }
        const advisorBox = document.getElementById('modal-par-advisor-box');
        if (advisorBox) advisorBox.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/dashboard_footer.php'; ?>
