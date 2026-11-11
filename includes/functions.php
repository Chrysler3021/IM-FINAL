<?php
/**
 * Shared Application Helpers, Image Upload Processing & View Formatting
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/icons.php';

/**
 * Safely handle uploaded product image, returning relative path or category SVG fallback
 */
function handle_product_image_upload(string $fieldName = 'image', string $category = '', ?string $existingUrl = null): string {
    if (
        isset($_FILES[$fieldName]) &&
        is_array($_FILES[$fieldName]) &&
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK &&
        !empty($_FILES[$fieldName]['tmp_name'])
    ) {
        $fileTmp  = $_FILES[$fieldName]['tmp_name'];
        $fileSize = (int)$_FILES[$fieldName]['size'];
        $origName = $_FILES[$fieldName]['name'] ?? 'image.png';

        if ($fileSize > 5 * 1024 * 1024) {
            throw new RuntimeException('Uploaded image exceeds the 5MB maximum file size.');
        }

        $allowedExtensions = [
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'gif'  => ['image/gif'],
            'svg'  => ['image/svg+xml', 'text/plain', 'text/xml', 'application/xml']
        ];

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowedExtensions)) {
            throw new RuntimeException('Unsupported image format. Upload JPG, PNG, WebP, GIF, or SVG.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmp) ?: '';

        if (!in_array($mimeType, $allowedExtensions[$ext], true)) {
            throw new RuntimeException('Uploaded file content did not match a valid image type.');
        }

        $uploadDir = dirname(__DIR__) . '/assets/uploads/products';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeFileName = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . '/' . $safeFileName;

        if (!move_uploaded_file($fileTmp, $destPath)) {
            throw new RuntimeException('Could not save uploaded image to server.');
        }

        return 'assets/uploads/products/' . $safeFileName;
    }

    if (!empty($existingUrl)) {
        return $existingUrl;
    }

    return get_default_category_image($category);
}

/**
 * Fallback crisp SVG illustration per product category
 */
function get_default_category_image(string $category): string {
    $cat = strtolower($category);
    if (str_contains($cat, 'water')) {
        return 'assets/images/products/water.svg';
    }
    if (str_contains($cat, 'juice')) {
        return 'assets/images/products/juice.svg';
    }
    if (str_contains($cat, 'drink') || str_contains($cat, 'soda') || str_contains($cat, 'cola')) {
        return 'assets/images/products/cola.svg';
    }
    if (str_contains($cat, 'candy') || str_contains($cat, 'choco')) {
        return 'assets/images/products/chocolate.svg';
    }
    if (str_contains($cat, 'pastr') || str_contains($cat, 'biscuit') || str_contains($cat, 'cookie')) {
        return 'assets/images/products/biscuits.svg';
    }
    return 'assets/images/products/chips.svg';
}

/**
 * Resolve display image URL for a product row
 */
function get_product_image(array $product): string {
    if (!empty($product['image_url'])) {
        return $product['image_url'];
    }
    return get_default_category_image($product['category'] ?? '');
}

/**
 * Resolve card media background color class per category
 */
function get_category_bg_class(string $category): string {
    $cat = strtolower($category);
    if (str_contains($cat, 'water')) {
        return 'bg-water';
    }
    if (str_contains($cat, 'juice')) {
        return 'bg-juice';
    }
    if (str_contains($cat, 'drink') || str_contains($cat, 'soda')) {
        return 'bg-soda';
    }
    if (str_contains($cat, 'candy') || str_contains($cat, 'choco')) {
        return 'bg-choco';
    }
    if (str_contains($cat, 'pastr') || str_contains($cat, 'biscuit')) {
        return 'bg-cookies';
    }
    return 'bg-chips';
}

/**
 * Normalize category filter slug for client-side filter pills
 */
function get_category_filter_key(string $category): string {
    $cat = strtolower($category);
    if (str_contains($cat, 'drink') || str_contains($cat, 'soda') || str_contains($cat, 'water') || str_contains($cat, 'juice')) {
        return 'drinks';
    }
    if (str_contains($cat, 'candy') || str_contains($cat, 'choco')) {
        return 'candy';
    }
    return 'snacks';
}

/**
 * Map inventory_logs.change_type to human-readable label & PostHog badge CSS class
 */
function get_reason_badge_info(string $changeType): array {
    $map = [
        'restock'     => ['label' => 'Restock load',       'class' => 'badge-restock-green'],
        'manual_sale' => ['label' => 'Sales tally',        'class' => 'badge-sale-blue'],
        'damaged'     => ['label' => 'Damaged or expired', 'class' => 'badge-loss-red'],
        'set_exact'   => ['label' => 'Physical audit',     'class' => 'badge-audit-purple'],
        'adjustment'  => ['label' => 'Record edit',        'class' => 'badge-initial-gray'],
        'initial'     => ['label' => 'Initial stock',      'class' => 'badge-initial-gray'],
    ];

    return $map[$changeType] ?? [
        'label' => ucfirst(str_replace('_', ' ', $changeType)),
        'class' => 'badge-initial-gray'
    ];
}

// -----------------------------------------------------------------------------
// Backwards-Compatible Helpers for Public Pages
// -----------------------------------------------------------------------------
function sanitize(string $data): string {
    return e(trim($data));
}

function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

function getCSRFToken(): string {
    return csrf_token();
}

function verifyCSRFToken(?string $token): bool {
    return verify_csrf($token);
}

function setFlashMessage(string $type, string $message): void {
    set_flash($type, $message);
}

function displayFlashMessage(): void {
    $flashes = get_flashes();
    foreach ($flashes as $f) {
        $type = e($f['type']);
        $msg  = e($f['message']);
        echo "<div class='dash-flash dash-flash-{$type}'><span>{$msg}</span><button type='button' class='btn-dismiss' onclick='this.parentElement.remove()'>✕</button></div>";
    }
}

// -----------------------------------------------------------------------------
// Core Shared Domain Helpers (Consolidated & Transaction-Safe)
// -----------------------------------------------------------------------------

/**
 * Canonical test for whether an item's inventory level is at or below par threshold.
 */
function is_low_stock(int $quantity, int $minThreshold): bool {
    return $quantity <= $minThreshold;
}

/**
 * Executes an atomic stock adjustment with transactional safety and audit logging.
 *
 * @param PDO $db
 * @param int $workspaceOwnerId
 * @param int $productId
 * @param string $changeType 'restock' | 'manual_sale' | 'damaged' | 'set_exact'
 * @param int $amount
 * @param string $notes
 * @param array|null $actorUser User record performing action
 * @return array
 * @throws RuntimeException on validation or permission failure
 */
function apply_stock_adjustment(
    PDO $db,
    int $workspaceOwnerId,
    int $productId,
    string $changeType,
    int $amount,
    string $notes = '',
    ?array $actorUser = null
): array {
    $amount = max(0, $amount);
    $validTypes = ['restock', 'manual_sale', 'damaged', 'set_exact'];
    if (!in_array($changeType, $validTypes, true)) {
        $changeType = 'restock';
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id AND user_id = :uid FOR UPDATE");
        $stmt->execute([':id' => $productId, ':uid' => $workspaceOwnerId]);
        $prod = $stmt->fetch();

        if (!$prod) {
            throw new RuntimeException('Product SKU could not be found.');
        }

        if ($actorUser !== null && is_staff($actorUser)) {
            if (!can_current_session_access_machine($db, (int)$prod['machine_id'])) {
                throw new RuntimeException('You are not assigned to service this vending machine.');
            }
        }

        $currentQty = (int)$prod['quantity'];
        $newQty = $currentQty;

        if ($changeType === 'restock') {
            $newQty = $currentQty + $amount;
        } elseif ($changeType === 'manual_sale' || $changeType === 'damaged') {
            $newQty = max(0, $currentQty - $amount);
        } elseif ($changeType === 'set_exact') {
            $newQty = $amount;
        }

        $delta = $newQty - $currentQty;

        $up = $db->prepare("UPDATE products SET quantity = :qty WHERE id = :id AND user_id = :uid");
        $up->execute([':qty' => $newQty, ':id' => $productId, ':uid' => $workspaceOwnerId]);

        $staffId = !empty($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : null;
        $actorName = !empty($_SESSION['staff_name'])
            ? $_SESSION['staff_name']
            : ($actorUser['name'] ?? ($_SESSION['user_name'] ?? 'Operator'));

        $defaultNotes = [
            'restock'     => 'Inventory restock addition',
            'manual_sale' => 'Manual cash-box / vend sales tally',
            'damaged'     => 'Removed damaged or expired stock',
            'set_exact'   => 'Physical cabinet count audit'
        ];

        $logNote = $notes !== '' ? $notes : ($defaultNotes[$changeType] ?? 'Manual stock adjustment');
        if ($actorUser !== null && is_staff($actorUser)) {
            $logNote = '[Staff: ' . $actorName . '] ' . $logNote;
        }

        $log = $db->prepare("
            INSERT INTO inventory_logs (
                user_id, staff_id, actor_name, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes
            ) VALUES (
                :uid, :sid, :actor, :pid, :mid, :pqty, :nqty, :dqty, :type, :notes
            )
        ");
        $log->execute([
            ':uid'   => $workspaceOwnerId,
            ':sid'   => $staffId,
            ':actor' => $actorName,
            ':pid'   => $productId,
            ':mid'   => (int)$prod['machine_id'],
            ':pqty'  => $currentQty,
            ':nqty'  => $newQty,
            ':dqty'  => $delta,
            ':type'  => $changeType,
            ':notes' => $logNote
        ]);

        $db->commit();

        $minThreshold = (int)$prod['min_threshold'];
        $isLow = is_low_stock($newQty, $minThreshold);

        return [
            'success'       => true,
            'product'       => $prod,
            'product_id'    => $productId,
            'previous_qty'  => $currentQty,
            'new_qty'       => $newQty,
            'delta'         => $delta,
            'min_threshold' => $minThreshold,
            'is_low_stock'  => $isLow,
            'message'       => "Stock updated for '{$prod['name']}': {$newQty} units recorded."
        ];
    } catch (Throwable $t) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $t;
    }
}

/**
 * Returns consolidated KPI metrics and sidebar badge counts in a single efficient query.
 */
function get_workspace_stats(PDO $db, int $workspaceOwnerId): array {
    static $statsCache = [];
    if (isset($statsCache[$workspaceOwnerId])) {
        return $statsCache[$workspaceOwnerId];
    }

    $pStmt = $db->prepare("
        SELECT
            COUNT(*) AS total_skus,
            COALESCE(SUM(quantity), 0) AS total_units,
            COALESCE(SUM(quantity * sell_price), 0) AS retail_value,
            COALESCE(SUM(CASE WHEN quantity <= min_threshold THEN 1 ELSE 0 END), 0) AS low_stock_count,
            COALESCE(SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count
        FROM products
        WHERE user_id = :uid
    ");
    $pStmt->execute([':uid' => $workspaceOwnerId]);
    $pStats = $pStmt->fetch() ?: [];

    $mStmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE user_id = :uid");
    $mStmt->execute([':uid' => $workspaceOwnerId]);
    $totalMachines = (int)$mStmt->fetchColumn();

    $sStmt = $db->prepare("SELECT COUNT(*) FROM staff_accounts WHERE operator_id = :uid");
    $sStmt->execute([':uid' => $workspaceOwnerId]);
    $totalStaff = (int)$sStmt->fetchColumn();

    $stats = [
        'total_machines'     => $totalMachines,
        'total_skus'         => (int)($pStats['total_skus'] ?? 0),
        'total_units'        => (int)($pStats['total_units'] ?? 0),
        'retail_value'       => (float)($pStats['retail_value'] ?? 0.0),
        'low_stock_count'    => (int)($pStats['low_stock_count'] ?? 0),
        'out_of_stock_count' => (int)($pStats['out_of_stock_count'] ?? 0),
        'total_staff'        => $totalStaff,
    ];

    $statsCache[$workspaceOwnerId] = $stats;
    return $stats;
}

/**
 * Atomically updates a staff member's machine access scope and assignments.
 */
function sync_staff_machine_access(
    PDO $db,
    int $staffId,
    int $operatorId,
    string $accessScope,
    array $selectedMachineIds
): void {
    $scope = $accessScope === 'selected' ? 'selected' : 'all';

    $db->beginTransaction();
    try {
        $up = $db->prepare("UPDATE staff_accounts SET access_scope = :scope WHERE id = :sid AND operator_id = :oid");
        $up->execute([':scope' => $scope, ':sid' => $staffId, ':oid' => $operatorId]);

        $del = $db->prepare("DELETE FROM staff_machines WHERE staff_id = :sid");
        $del->execute([':sid' => $staffId]);

        if ($scope === 'selected' && !empty($selectedMachineIds)) {
            $insMach = $db->prepare("
                INSERT IGNORE INTO staff_machines (staff_id, machine_id)
                SELECT :sid, id FROM machines WHERE id = :mid AND user_id = :oid
            ");
            foreach ($selectedMachineIds as $mid) {
                $insMach->execute([
                    ':sid' => $staffId,
                    ':mid' => (int)$mid,
                    ':oid' => $operatorId
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $t) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $t;
    }
}

/**
 * Returns public, guest-safe vending machine and stock records for consumer discovery.
 * Supports searching by machine name, location, identifier, or stocked product name/category.
 * Strictly strips all operator-sensitive data (cost_price, min_threshold, user_id, internal notes).
 */
function get_public_vending_machines(PDO $db, string $query = '', string $category = ''): array {
    $cleanQuery = strtolower(trim($query));
    $cleanCategory = strtolower(trim($category));
    if ($cleanCategory === 'all') {
        $cleanCategory = '';
    }

    $mStmt = $db->query("
        SELECT id, name, identifier, location, status
        FROM machines
        WHERE status = 'active'
        ORDER BY id ASC
    ");
    $machines = $mStmt->fetchAll() ?: [];
    if (empty($machines)) {
        return [];
    }

    $pStmt = $db->query("
        SELECT id, machine_id, name, sku, category, quantity, min_threshold, sell_price, image_url
        FROM products
        ORDER BY machine_id ASC, quantity DESC, name ASC
    ");
    $allProducts = $pStmt->fetchAll() ?: [];

    $productsByMachine = [];
    foreach ($allProducts as $prod) {
        $mid = (int)$prod['machine_id'];
        $qty = (int)$prod['quantity'];
        $minThresh = (int)$prod['min_threshold'];

        $stockStatus = 'in_stock';
        if ($qty <= 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($qty <= $minThresh) {
            $stockStatus = 'low_stock';
        }

        $isMatchedProduct = false;
        if ($cleanQuery !== '') {
            $pNameLower = strtolower((string)$prod['name']);
            $pCatLower  = strtolower((string)$prod['category']);
            $pSkuLower  = strtolower((string)$prod['sku']);
            if (
                str_contains($pNameLower, $cleanQuery) ||
                str_contains($pCatLower, $cleanQuery) ||
                str_contains($pSkuLower, $cleanQuery)
            ) {
                $isMatchedProduct = true;
            }
        }

        $productsByMachine[$mid][] = [
            'id'           => (int)$prod['id'],
            'name'         => (string)$prod['name'],
            'category'     => (string)$prod['category'],
            'sell_price'   => (float)$prod['sell_price'],
            'quantity'     => $qty,
            'stock_status' => $stockStatus,
            'image_url'    => get_product_image($prod),
            'matched'      => $isMatchedProduct,
        ];
    }

    $distancePresets = [
        '120m away',
        '250m away',
        '380m away',
        '0.5 km away',
        '0.7 km away',
        '0.9 km away',
        '1.2 km away',
        '1.5 km away',
    ];

    $result = [];
    foreach ($machines as $idx => $m) {
        $mid = (int)$m['id'];
        $mProds = $productsByMachine[$mid] ?? [];

        if ($cleanCategory !== '') {
            $hasCategoryMatch = false;
            foreach ($mProds as $p) {
                if (str_contains(strtolower($p['category']), $cleanCategory) || str_contains(strtolower($p['name']), $cleanCategory)) {
                    $hasCategoryMatch = true;
                    break;
                }
            }
            if (!$hasCategoryMatch) {
                continue;
            }
        }

        if ($cleanQuery !== '') {
            $mNameLower = strtolower((string)$m['name']);
            $mLocLower  = strtolower((string)$m['location']);
            $mIdenLower = strtolower((string)($m['identifier'] ?? ''));

            $machineMatches = str_contains($mNameLower, $cleanQuery)
                || str_contains($mLocLower, $cleanQuery)
                || str_contains($mIdenLower, $cleanQuery);

            $productMatches = false;
            foreach ($mProds as $p) {
                if (!empty($p['matched'])) {
                    $productMatches = true;
                    break;
                }
            }

            if (!$machineMatches && !$productMatches) {
                continue;
            }

            if ($productMatches) {
                usort($mProds, function (array $a, array $b): int {
                    if ($a['matched'] !== $b['matched']) {
                        return $a['matched'] ? -1 : 1;
                    }
                    return $b['quantity'] <=> $a['quantity'];
                });
            }
        }

        $inStockCount = 0;
        $totalUnits   = 0;
        foreach ($mProds as $p) {
            if ($p['quantity'] > 0) {
                $inStockCount++;
                $totalUnits += $p['quantity'];
            }
        }

        $distanceLabel = $distancePresets[$idx % count($distancePresets)];
        $indoorSpots = [
            'Level 1 • East Concourse',
            'Level 2 • North Breakroom',
            'Ground Floor • Main Lobby',
            'Level 3 • Study Commons',
        ];
        $indoorPlacement = $indoorSpots[$idx % count($indoorSpots)];
        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode((string)$m['location'] . ' Vending');

        $result[] = [
            'machine_id'       => $mid,
            'machine_name'     => (string)$m['name'],
            'identifier'       => (string)($m['identifier'] ?: ('VF-M' . str_pad((string)$mid, 2, '0', STR_PAD_LEFT))),
            'location'         => (string)$m['location'],
            'indoor_placement' => $indoorPlacement,
            'distance'         => $distanceLabel,
            'maps_url'         => $mapsUrl,
            'payments'         => ['Apple Pay / NFC', 'Cards', 'Cash'],
            'status'           => (string)$m['status'],
            'total_products'   => count($mProds),
            'in_stock_count'   => $inStockCount,
            'total_units'      => $totalUnits,
            'products'         => $mProds,
        ];
    }

    return $result;
}
