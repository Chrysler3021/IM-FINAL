<?php
/**
 * Authentication, Session Management, CSRF & Flash Helpers
 * VendFlow Operator Workspace
 */

require_once __DIR__ . '/db.php';

function start_session_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
        }
        session_start();
    }
}

start_session_safe();

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && (int)$cachedUser['id'] === (int)$_SESSION['user_id']) {
        return $cachedUser;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT id, name, email, business_name, plan_tier, custom_machine_limit, custom_staff_limit, custom_price, license_key, currency, default_threshold, role, operator_id, can_manage_machines, can_manage_prices, status, created_at
            FROM users
            WHERE id = :id
        ");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            if (!empty($_SESSION['staff_id'])) {
                $user['is_staff'] = true;
                $user['staff_id'] = (int)$_SESSION['staff_id'];
                $user['operator_name'] = $user['name'];
                $user['name'] = $_SESSION['user_name'] ?? $user['name'];
                $user['role'] = 'staff';
                $user['operator_id'] = (int)$user['id'];
            }
            $cachedUser = $user;
            return $user;
        }
    } catch (Throwable $e) {
        error_log("Auth user lookup error: " . $e->getMessage());
    }

    return null;
}

function is_staff(?array $user = null): bool {
    if (!empty($_SESSION['staff_id']) || ($_SESSION['user_role'] ?? '') === 'staff') {
        return true;
    }
    $u = $user ?? current_user();
    return ($u !== null && ($u['role'] ?? '') === 'staff');
}

function is_operator(?array $user = null): bool {
    return !is_staff($user);
}

function current_staff(): ?array {
    if (empty($_SESSION['staff_id'])) {
        return null;
    }
    static $cachedStaff = null;
    if ($cachedStaff !== null && (int)$cachedStaff['id'] === (int)$_SESSION['staff_id']) {
        return $cachedStaff;
    }
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM staff_accounts WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute([':id' => (int)$_SESSION['staff_id']]);
        $cachedStaff = $stmt->fetch();
        return $cachedStaff;
    } catch (Throwable $e) {
        return null;
    }
}

function is_staff_username_taken(PDO $db, string $username, ?int $excludeStaffId = null): bool {
    $clean = trim($username);
    if ($clean === '') {
        return true;
    }
    if ($excludeStaffId !== null && $excludeStaffId > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM staff_accounts WHERE LOWER(username) = LOWER(:u) AND id != :ex");
        $stmt->execute([':u' => $clean, ':ex' => $excludeStaffId]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM staff_accounts WHERE LOWER(username) = LOWER(:u)");
        $stmt->execute([':u' => $clean]);
    }
    if ((int)$stmt->fetchColumn() > 0) {
        return true;
    }
    $uStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:u)");
    $uStmt->execute([':u' => $clean]);
    return (int)$uStmt->fetchColumn() > 0;
}

function login_staff(array $staff, array $operator): void {
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']        = (int)$operator['id'];
    $_SESSION['staff_id']       = (int)$staff['id'];
    $_SESSION['staff_username'] = $staff['username'];
    $_SESSION['staff_name']     = $staff['name'];
    $_SESSION['user_name']      = $staff['name'];
    $_SESSION['user_email']     = $operator['email'];
    $_SESSION['plan_tier']      = $operator['plan_tier'] ?? 'solo';
    $_SESSION['user_role']      = 'staff';
}

/**
 * Return the list of machine IDs that a staff member is authorized to access and adjust.
 */
function get_staff_accessible_machine_ids(PDO $db, array $staff): array {
    $operatorId = (int)$staff['operator_id'];
    $scope = $staff['access_scope'] ?? 'all';

    if ($scope === 'selected') {
        $stmt = $db->prepare("
            SELECT m.id
            FROM machines m
            INNER JOIN staff_machines sm ON sm.machine_id = m.id
            WHERE m.user_id = :oid AND sm.staff_id = :sid
            ORDER BY m.name ASC
        ");
        $stmt->execute([
            ':oid' => $operatorId,
            ':sid' => (int)$staff['id']
        ]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $stmt = $db->prepare("SELECT id FROM machines WHERE user_id = :oid ORDER BY name ASC");
    $stmt->execute([':oid' => $operatorId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Check whether the current session (Operator or Staff) is authorized to access/adjust a specific machine.
 */
function can_current_session_access_machine(PDO $db, int $machineId): bool {
    if (!is_logged_in()) {
        return false;
    }

    if (is_staff()) {
        $staff = current_staff();
        if (!$staff) {
            return false;
        }
        $allowedIds = get_staff_accessible_machine_ids($db, $staff);
        return in_array($machineId, $allowedIds, true);
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare("SELECT COUNT(*) FROM machines WHERE id = :mid AND user_id = :uid");
    $stmt->execute([':mid' => $machineId, ':uid' => $userId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Require authentication for the Staff Restock Dashboard (accessible to logged-in staff or operators).
 */
function require_staff_workspace_auth(): array {
    if (!is_logged_in()) {
        $currentUrl = urlencode(basename($_SERVER['REQUEST_URI'] ?? 'staff-dashboard.php'));
        header("Location: login.php?redirect=" . $currentUrl);
        exit;
    }

    $user = current_user();
    if (!$user) {
        logout_user();
        header("Location: login.php?error=invalid_session");
        exit;
    }

    return $user;
}

/**
 * Returns the effective operator user ID for querying workspace inventory.
 * If user is staff, returns their company operator's ID.
 */
function get_workspace_owner_id(?array $user = null): int {
    $u = $user ?? current_user();
    if (!$u) return 0;
    if (($u['role'] ?? '') === 'staff' && !empty($u['operator_id'])) {
        return (int)$u['operator_id'];
    }
    return (int)$u['id'];
}

function can_manage_machines(?array $user = null): bool {
    $u = $user ?? current_user();
    if (!$u) return false;
    if (($u['role'] ?? '') === 'operator' || ($u['role'] ?? '') === 'superadmin') {
        return true;
    }
    return !empty($u['can_manage_machines']);
}

function can_manage_prices(?array $user = null): bool {
    $u = $user ?? current_user();
    if (!$u) return false;
    if (($u['role'] ?? '') === 'operator' || ($u['role'] ?? '') === 'superadmin') {
        return true;
    }
    return !empty($u['can_manage_prices']);
}

function login_user(array $user): void {
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['plan_tier']  = $user['plan_tier'] ?? 'solo';
    $_SESSION['user_role']  = $user['role'] ?? 'operator';
}

/**
 * 1-Click Instant Demo Login helper
 */
function login_demo_user(): array {
    $db = get_db();
    $demoUser = ensure_demo_user($db);
    login_user($demoUser);
    return $demoUser;
}

/**
 * Superadmin authentication & demo helpers
 */
function is_superadmin(): bool {
    $user = current_user();
    return ($user !== null && ($user['role'] ?? '') === 'superadmin');
}

function login_demo_superadmin(): array {
    $db = get_db();
    $admin = ensure_superadmin_user($db);
    login_user($admin);
    return $admin;
}

function require_superadmin(): array {
    // 1-Click Instant Superadmin Demo Entry
    if (isset($_GET['demo_admin']) && $_GET['demo_admin'] === '1') {
        unset($_SESSION['impersonator_id']);
        $admin = login_demo_superadmin();
        set_flash('success', "Signed into Superadmin console as {$admin['name']}.");
        $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: " . $cleanUrl);
        exit;
    }

    // If Superadmin navigated directly to superadmin.php while impersonating a client, restore Superadmin session
    if (isset($_SESSION['impersonator_id'])) {
        $realAdminId = (int)$_SESSION['impersonator_id'];
        unset($_SESSION['impersonator_id']);
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND role = 'superadmin' LIMIT 1");
        $stmt->execute([':id' => $realAdminId]);
        $realAdmin = $stmt->fetch();
        if ($realAdmin) {
            login_user($realAdmin);
            header("Location: superadmin.php");
            exit;
        }
    }

    $user = require_auth();
    if (($user['role'] ?? '') !== 'superadmin') {
        set_flash('error', 'Access restricted. Operator accounts cannot access the Platform Superadmin console.');
        header("Location: dashboard.php");
        exit;
    }

    return $user;
}

function logout_user(): void {
    $_SESSION = [];
    if (!headers_sent() && ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function require_auth(): array {
    // Allow 1-click demo login directly via ?demo=1 on any workspace page
    if (isset($_GET['demo']) && $_GET['demo'] === '1') {
        $demoUser = login_demo_user();
        set_flash('success', "Signed into demo workspace as {$demoUser['name']}.");
        $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: " . $cleanUrl);
        exit;
    }

    if (!is_logged_in()) {
        $currentUrl = urlencode(basename($_SERVER['REQUEST_URI']));
        header("Location: login.php?redirect=" . $currentUrl);
        exit;
    }

    $user = current_user();
    if (!$user) {
        logout_user();
        header("Location: login.php?error=invalid_session");
        exit;
    }

    if (is_staff($user)) {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        $allowedStaffPages = ['inventory.php', 'machines.php', 'low-stock.php', 'audit-log.php'];
        if (!in_array($currentPage, $allowedStaffPages, true)) {
            header("Location: inventory.php");
            exit;
        }
    }

    if (($user['status'] ?? 'active') === 'suspended' && ($user['role'] ?? '') !== 'superadmin' && !isset($_SESSION['impersonator_id'])) {
        logout_user();
        start_session_safe();
        set_flash('error', 'Your operator workspace access has been suspended. Please contact platform support.');
        header("Location: login.php");
        exit;
    }

    return $user;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = [
        'type'    => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

function get_flashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function e(?string $val = null): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function format_currency(float $amount, string $symbol = '$'): string {
    return $symbol . number_format($amount, 2);
}

/**
 * Check if the currently active user or provided user array is the shared demo account
 */
function is_demo_user(?array $user = null): bool {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) {
        return false;
    }
    return strtolower(trim($user['email'] ?? '')) === 'demo@vendflow.io';
}