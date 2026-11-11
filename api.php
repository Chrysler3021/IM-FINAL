<?php
/**
 * VendFlow JSON API Endpoint for AJAX Actions
 * Pure Vanilla PHP + MySQL PDO Prepared Statements
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid operator session']);
    exit;
}

$db = get_db();
$userId = get_workspace_owner_id($user);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Adjust Stock via AJAX
if ($action === 'adjust_stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security token invalid']);
        exit;
    }

    $prodId = (int)($_POST['product_id'] ?? 0);
    $type   = $_POST['change_type'] ?? 'restock';
    $amount = max(0, (int)($_POST['amount'] ?? 0));
    $notes  = trim($_POST['notes'] ?? '');

    try {
        $result = apply_stock_adjustment($db, $userId, $prodId, $type, $amount, $notes, $user);
        echo json_encode($result);
    } catch (RuntimeException $re) {
        $status = str_contains($re->getMessage(), 'not found') ? 404 : 403;
        http_response_code($status);
        echo json_encode(['success' => false, 'error' => $re->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An error occurred while updating stock.']);
    }
    exit;
}

// 2. Workspace Summary Stats
if ($action === 'stats') {
    $stats = get_workspace_stats($db, $userId);

    echo json_encode([
        'success'     => true,
        'machines'    => $stats['total_machines'],
        'products'    => $stats['total_skus'],
        'total_units' => $stats['total_units'],
        'low_stock'   => $stats['low_stock_count'],
        'plan_tier'   => $user['plan_tier'] ?? 'unlimited',
        'license_key' => $user['license_key'] ?? '',
    ]);
    exit;
}

// 3. Active License & Latest Receipt Verification
if ($action === 'license') {
    $lStmt = $db->prepare("SELECT license_key, plan_name, status, activated_at FROM license_keys WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $lStmt->execute([':uid' => $userId]);
    $licRow = $lStmt->fetch() ?: null;

    $bStmt = $db->prepare("SELECT transaction_ref, amount, currency, payment_method, status, created_at FROM billing_transactions WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
    $bStmt->execute([':uid' => $userId]);
    $txnRow = $bStmt->fetch() ?: null;

    echo json_encode([
        'success'         => true,
        'plan_tier'       => 'unlimited',
        'machines_limit'  => 'unlimited',
        'products_limit'  => 'unlimited',
        'license'         => $licRow,
        'latest_receipt'  => $txnRow,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown API action']);
