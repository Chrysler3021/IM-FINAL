<?php
/**
 * VendFlow Database Connection, Relational Schema & Provisioning Engine
 * 100% Pure Vanilla PHP 8.2 + MySQL / MariaDB PDO
 */

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'vendflow');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

require_once __DIR__ . '/mail.php';

// Default Baseline Tier Constants (Solo Tier — backwards-compatible defaults)
if (!defined('VENDFLOW_PLAN_TIER'))  define('VENDFLOW_PLAN_TIER', 'solo');
if (!defined('VENDFLOW_PLAN_NAME'))  define('VENDFLOW_PLAN_NAME', 'VendFlow Solo License');
if (!defined('VENDFLOW_PLAN_PRICE')) define('VENDFLOW_PLAN_PRICE', 19.00);

if (!defined('VENDFLOW_ADDON_MACHINE_PRICE')) define('VENDFLOW_ADDON_MACHINE_PRICE', 1.00);
if (!defined('VENDFLOW_ADDON_STAFF_PRICE'))   define('VENDFLOW_ADDON_STAFF_PRICE', 2.00);

/**
 * Canonical registry of the VendFlow software license tiers.
 * Supports Solo, Business, Max, and Enterprise fleet capacity.
 */
function get_license_plans(): array {
    return [
        'solo' => [
            'tier'          => 'solo',
            'label'         => 'Solo',
            'plan_name'     => 'VendFlow Solo License',
            'key_prefix'    => 'VF-SOL',
            'price'         => 19.00,
            'machine_limit' => 4,
            'staff_limit'   => 1,
            'badge'         => 'SOLO OPERATOR',
            'featured'      => false,
            'tagline'       => 'Ideal for independent operators running a compact local route.',
        ],
        'business' => [
            'tier'          => 'business',
            'label'         => 'Business',
            'plan_name'     => 'VendFlow Business License',
            'key_prefix'    => 'VF-BUS',
            'price'         => 35.00,
            'machine_limit' => 12,
            'staff_limit'   => 4,
            'badge'         => 'MOST POPULAR',
            'featured'      => true,
            'tagline'       => 'Built for expanding vending routes with multiple restocking staff.',
        ],
        'max' => [
            'tier'          => 'max',
            'label'         => 'Max',
            'plan_name'     => 'VendFlow Max License',
            'key_prefix'    => 'VF-MAX',
            'price'         => 99.00,
            'machine_limit' => 50,
            'staff_limit'   => 18,
            'badge'         => 'FLEET SCALE',
            'featured'      => false,
            'tagline'       => 'High-capacity fleet scale for multi-route regional operations.',
        ],
        'enterprise' => [
            'tier'          => 'enterprise',
            'label'         => 'Enterprise',
            'plan_name'     => 'VendFlow Enterprise License',
            'key_prefix'    => 'VF-ENT',
            'price'         => 199.00,
            'machine_limit' => 100,
            'staff_limit'   => 30,
            'badge'         => 'ENTERPRISE FLEET',
            'featured'      => false,
            'tagline'       => 'Tailored high-volume capacity and fleet infrastructure.',
        ],
    ];
}

/**
 * Calculates unified capacity limits, add-on costs, total monthly fee, and smart tier recommendations.
 * Central single source of truth for both self-service registration and superadmin adjustments.
 *
 * @param string   $tier              'solo' | 'business' | 'max' | 'enterprise'
 * @param int|null $requestedMachines Total desired machines (defaults to plan baseline)
 * @param int|null $requestedStaff    Total desired staff (defaults to plan baseline)
 * @return array
 */
function calculate_tiered_capacity_price(string $tier, ?int $requestedMachines = null, ?int $requestedStaff = null): array {
    $plans = get_license_plans();
    $cleanTier = strtolower(trim($tier));
    if (!isset($plans[$cleanTier])) {
        $cleanTier = 'solo';
    }
    $plan = $plans[$cleanTier];

    $baseMachines = (int)$plan['machine_limit'];
    $baseStaff    = (int)$plan['staff_limit'];
    $basePrice    = (float)$plan['price'];

    // Enforce plan floor
    $finalMachines = ($requestedMachines !== null && $requestedMachines > 0)
        ? max($baseMachines, (int)$requestedMachines)
        : $baseMachines;

    $finalStaff = ($requestedStaff !== null && $requestedStaff > 0)
        ? max($baseStaff, (int)$requestedStaff)
        : $baseStaff;

    $extraMachines = max(0, $finalMachines - $baseMachines);
    $extraStaff    = max(0, $finalStaff - $baseStaff);

    $extraMachineCost = (float)($extraMachines * VENDFLOW_ADDON_MACHINE_PRICE);
    $extraStaffCost   = (float)($extraStaff * VENDFLOW_ADDON_STAFF_PRICE);

    $totalPrice = round($basePrice + $extraMachineCost + $extraStaffCost, 2);

    // Smart tier recommendation: Check if next tier offers more capacity for less or equal cost
    $recommendedTier = null;
    $recommendedTip  = null;

    if ($cleanTier === 'solo') {
        if ($totalPrice >= 35.00 || $finalMachines >= 12 || $finalStaff >= 4) {
            $recommendedTier = 'business';
            $recommendedTip = 'Upgrading to Business ($35.00/mo) includes 12 machines & 4 staff by default and will save you money as your fleet expands.';
        }
    } elseif ($cleanTier === 'business') {
        if ($totalPrice >= 99.00 || $finalMachines >= 50 || $finalStaff >= 18) {
            $recommendedTier = 'max';
            $recommendedTip = 'Upgrading to Max ($99.00/mo) includes 50 machines & 18 staff by default and provides better economy of scale.';
        }
    } elseif ($cleanTier === 'max') {
        if ($totalPrice >= 199.00 || $finalMachines >= 100 || $finalStaff >= 30) {
            $recommendedTier = 'enterprise';
            $recommendedTip = 'Upgrading to Enterprise ($199.00/mo) includes 100 machines & 30 staff by default with priority fleet support.';
        }
    }

    return [
        'tier'                => $cleanTier,
        'label'               => $plan['label'],
        'plan_name'           => $plan['plan_name'],
        'key_prefix'          => $plan['key_prefix'],
        'base_price'          => $basePrice,
        'base_machines'       => $baseMachines,
        'base_staff'          => $baseStaff,
        'final_machines'      => $finalMachines,
        'final_staff'         => $finalStaff,
        'extra_machines'      => $extraMachines,
        'extra_staff'         => $extraStaff,
        'extra_machine_cost'  => $extraMachineCost,
        'extra_staff_cost'    => $extraStaffCost,
        'total_price'         => $totalPrice,
        'recommended_tier'    => $recommendedTier,
        'recommended_tip'     => $recommendedTip,
    ];
}

/**
 * Normalizes a requested self-service tier slug to a valid public tier ('solo' | 'business' | 'max' | 'enterprise').
 */
function normalize_self_service_tier(?string $tier): string {
    $clean = strtolower(trim((string)$tier));
    $plans = get_license_plans();
    return isset($plans[$clean]) ? $clean : 'solo';
}

/**
 * Resolves effective plan metadata, monthly price, machine limit, and staff limit for any user row.
 * Supports Solo, Business, Max, and admin-provisioned Enterprise custom limits.
 */
function get_user_plan_info(?array $user): array {
    $plans = get_license_plans();
    $rawTier = strtolower(trim((string)($user['plan_tier'] ?? 'solo')));

    if ($rawTier === 'enterprise') {
        $customMachines = isset($user['custom_machine_limit']) && $user['custom_machine_limit'] !== null
            ? max(1, (int)$user['custom_machine_limit'])
            : 250;
        $customStaff = isset($user['custom_staff_limit']) && $user['custom_staff_limit'] !== null
            ? max(1, (int)$user['custom_staff_limit'])
            : 100;
        $customPrice = isset($user['custom_price']) && $user['custom_price'] !== null
            ? (float)$user['custom_price']
            : 199.00;

        return [
            'tier'          => 'enterprise',
            'label'         => 'Enterprise',
            'plan_name'     => 'VendFlow Enterprise License',
            'key_prefix'    => 'VF-ENT',
            'price'         => $customPrice,
            'machine_limit' => $customMachines,
            'staff_limit'   => $customStaff,
            'badge'         => 'CUSTOM ENTERPRISE',
            'featured'      => false,
            'is_custom'     => true,
            'tagline'       => 'Admin-provisioned enterprise fleet capacity and custom limits.',
        ];
    }

    $base = $plans[$rawTier] ?? $plans['solo'];
    if (isset($user['custom_machine_limit']) && $user['custom_machine_limit'] !== null) {
        $base['machine_limit'] = max(1, (int)$user['custom_machine_limit']);
    }
    if (isset($user['custom_staff_limit']) && $user['custom_staff_limit'] !== null) {
        $base['staff_limit'] = max(1, (int)$user['custom_staff_limit']);
    }
    if (isset($user['custom_price']) && $user['custom_price'] !== null) {
        $base['price'] = (float)$user['custom_price'];
    }
    $base['is_custom'] = false;

    return $base;
}

/**
 * Fetches effective plan limits directly from the workspace owner's record in MySQL.
 */
function get_workspace_plan_info(PDO $pdo, int $workspaceOwnerId): array {
    static $planCache = [];
    if (isset($planCache[$workspaceOwnerId])) {
        return $planCache[$workspaceOwnerId];
    }
    $stmt = $pdo->prepare("
        SELECT id, plan_tier, custom_machine_limit, custom_staff_limit, custom_price
        FROM users
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $workspaceOwnerId]);
    $row = $stmt->fetch() ?: null;
    $info = get_user_plan_info($row);
    $planCache[$workspaceOwnerId] = $info;
    return $info;
}

/**
 * Returns shared PDO connection, auto-creating database & 9-table schema if missing.
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            initialize_schema($pdo);
        } catch (PDOException $e) {
            if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
                    $serverPdo = new PDO($serverDsn, DB_USER, DB_PASS, $options);
                    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                    initialize_schema($pdo);
                } catch (PDOException $inner) {
                    die("Database connection failed: " . htmlspecialchars($inner->getMessage()));
                }
            } else {
                die("Database connection failed: " . htmlspecialchars($e->getMessage()));
            }
        }
    }

    return $pdo;
}

/**
 * Non-fatal connection helper for public marketing pages
 */
function getDBConnection(): ?PDO {
    try {
        return get_db();
    } catch (Throwable $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Ensure all 9 relational VendFlow tables exist and stay migrated
 */
function initialize_schema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    // 1. Users table (Operators & Superadmin)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            business_name VARCHAR(255) NULL,
            plan_tier VARCHAR(50) NOT NULL DEFAULT 'solo',
            custom_machine_limit INT NULL,
            custom_staff_limit INT NULL,
            custom_price DECIMAL(10,2) NULL,
            license_key VARCHAR(100) NULL,
            currency VARCHAR(10) NOT NULL DEFAULT '$',
            default_threshold INT NOT NULL DEFAULT 10,
            role VARCHAR(50) NOT NULL DEFAULT 'operator',
            operator_id INT NULL,
            can_manage_machines TINYINT(1) NOT NULL DEFAULT 0,
            can_manage_prices TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            email_verified_at DATETIME NULL,
            verification_token VARCHAR(100) NULL,
            last_login_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Migrate columns if upgrading from an older table version
    $alterStatements = [
        "ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NULL AFTER name",
        "ALTER TABLE users ADD COLUMN middle_name VARCHAR(100) NULL AFTER first_name",
        "ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NULL AFTER middle_name",
        "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'operator'",
        "ALTER TABLE users ADD COLUMN operator_id INT NULL",
        "ALTER TABLE users ADD COLUMN can_manage_machines TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN can_manage_prices TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active'",
        "ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL",
        "ALTER TABLE users ADD COLUMN verification_token VARCHAR(100) NULL",
        "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL",
        "ALTER TABLE users ADD COLUMN custom_machine_limit INT NULL AFTER plan_tier",
        "ALTER TABLE users ADD COLUMN custom_staff_limit INT NULL AFTER custom_machine_limit",
        "ALTER TABLE users ADD COLUMN custom_price DECIMAL(10,2) NULL AFTER custom_staff_limit",
    ];
    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {}
    }

    // 2. Software License Keys Registry
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS license_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_key VARCHAR(100) NOT NULL UNIQUE,
            user_id INT NULL,
            plan_name VARCHAR(100) NOT NULL DEFAULT 'VendFlow Unlimited License',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            issued_by VARCHAR(50) NOT NULL DEFAULT 'self_checkout',
            activated_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_license_user (user_id),
            INDEX idx_license_status (status),
            CONSTRAINT fk_license_keys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Simulated Billing & License Purchase Transactions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS billing_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_ref VARCHAR(64) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            license_key VARCHAR(100) NOT NULL,
            plan_name VARCHAR(100) NOT NULL DEFAULT 'VendFlow Unlimited License',
            amount DECIMAL(10,2) NOT NULL DEFAULT 49.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            payment_method VARCHAR(100) NOT NULL DEFAULT 'Card •••• 4242 (Simulated)',
            billing_name VARCHAR(255) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'paid',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_billing_user (user_id),
            CONSTRAINT fk_billing_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Platform-Wide System & Security Audit Logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT NULL,
            actor_name VARCHAR(255) NOT NULL,
            actor_role VARCHAR(50) NOT NULL DEFAULT 'operator',
            event_type VARCHAR(80) NOT NULL,
            target_user_id INT NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_plat_audit_actor (actor_user_id),
            INDEX idx_plat_audit_event (event_type),
            CONSTRAINT fk_plat_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Vending Machines Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS machines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            identifier VARCHAR(100) NULL,
            location VARCHAR(255) NOT NULL,
            notes TEXT NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_machines_user (user_id),
            CONSTRAINT fk_machines_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Products & Stock Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            machine_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NOT NULL,
            category VARCHAR(100) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            min_threshold INT NOT NULL DEFAULT 10,
            cost_price DECIMAL(10,2) DEFAULT 0.00,
            sell_price DECIMAL(10,2) DEFAULT 0.00,
            image_url VARCHAR(255) NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_products_user (user_id),
            INDEX idx_products_machine (machine_id),
            CONSTRAINT fk_products_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_products_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 7. Inventory Adjustment Audit Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            staff_id INT NULL,
            actor_name VARCHAR(255) NULL,
            product_id INT NOT NULL,
            machine_id INT NOT NULL,
            previous_qty INT NOT NULL,
            new_qty INT NOT NULL,
            change_qty INT NOT NULL,
            change_type VARCHAR(50) NOT NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_logs_user (user_id),
            INDEX idx_logs_staff (staff_id),
            INDEX idx_logs_product (product_id),
            INDEX idx_logs_machine (machine_id),
            CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_logs_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_logs_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Migration patch for existing setups
    try {
        $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN staff_id INT NULL AFTER user_id");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN actor_name VARCHAR(255) NULL AFTER staff_id");
    } catch (Throwable $e) {}

    // 8. Password Resets Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_resets_token (token),
            INDEX idx_resets_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 9. Public Contact & Support Inquiries Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'open'");
    } catch (Throwable $e) {}

    // 10. Staff Accounts & Machine Assignments Tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            operator_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            access_scope VARCHAR(30) NOT NULL DEFAULT 'all',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_login_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_staff_operator (operator_id),
            CONSTRAINT fk_staff_operator FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_machines (
            staff_id INT NOT NULL,
            machine_id INT NOT NULL,
            PRIMARY KEY (staff_id, machine_id),
            CONSTRAINT fk_sm_staff FOREIGN KEY (staff_id) REFERENCES staff_accounts(id) ON DELETE CASCADE,
            CONSTRAINT fk_sm_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Migrate any legacy 'unlimited' or unrecognized operator tiers to 'solo'
    $pdo->exec("
        UPDATE users
        SET plan_tier = 'solo'
        WHERE COALESCE(role, 'operator') != 'superadmin'
          AND LOWER(COALESCE(plan_tier, '')) NOT IN ('solo', 'business', 'max', 'enterprise')
    ");

    // Ensure baseline demo operator & superadmin exist
    ensure_demo_user($pdo);
    ensure_superadmin_user($pdo);

    // Ensure every operator account has synchronized license_keys & billing_transactions records
    sync_existing_operator_licenses($pdo);
}

/**
 * Generates a unique cryptographic VendFlow software license key matching the selected tier
 */
function generate_license_key(string $plan = 'solo'): string {
    $clean = strtolower(trim($plan));
    $prefixMap = [
        'solo'       => 'VF-SOL',
        'business'   => 'VF-BUS',
        'max'        => 'VF-MAX',
        'enterprise' => 'VF-ENT',
    ];
    $prefix = $prefixMap[$clean] ?? 'VF-SOL';
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(2)));
}

/**
 * Generates a unique simulated billing receipt reference
 */
function generate_transaction_ref(): string {
    return 'VF-TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Appends an immutable record to platform_audit_logs
 */
function log_platform_event(
    PDO $pdo,
    ?int $actorId,
    string $actorName,
    string $actorRole,
    string $eventType,
    string $description,
    ?int $targetUserId = null
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO platform_audit_logs (actor_user_id, actor_name, actor_role, event_type, target_user_id, description, ip_address)
            VALUES (:aid, :aname, :arole, :etype, :tid, :desc, :ip)
        ");
        $stmt->execute([
            ':aid'   => $actorId,
            ':aname' => $actorName,
            ':arole' => $actorRole,
            ':etype' => $eventType,
            ':tid'   => $targetUserId,
            ':desc'  => $description,
            ':ip'    => $ip,
        ]);
    } catch (Throwable $e) {
        error_log("Platform audit log error: " . $e->getMessage());
    }
}

/**
 * Atomically creates a new Operator account + simulated license purchase + active license key
 *
 * @param PDO   $pdo
 * @param array $payload {name, email, password, business_name, payment_method, seed_demo}
 * @return array {user, license_key, transaction_ref}
 */
function provision_operator_account(PDO $pdo, array $payload): array {
    $name          = trim($payload['name'] ?? '');
    $firstName     = isset($payload['first_name']) && trim((string)$payload['first_name']) !== '' ? trim((string)$payload['first_name']) : null;
    $middleName    = isset($payload['middle_name']) && trim((string)$payload['middle_name']) !== '' ? trim((string)$payload['middle_name']) : null;
    $lastName      = isset($payload['last_name']) && trim((string)$payload['last_name']) !== '' ? trim((string)$payload['last_name']) : null;
    $email         = strtolower(trim($payload['email'] ?? ''));
    $password      = $payload['password'] ?? '';
    $businessName  = trim($payload['business_name'] ?? '');
    $paymentMethod = trim($payload['payment_method'] ?? 'Visa •••• 4242 (Simulated)');
    $seedDemo      = !empty($payload['seed_demo']);
    $planTier      = normalize_self_service_tier($payload['plan_tier'] ?? 'solo');

    $rawMach       = isset($payload['custom_machine_limit']) && is_numeric($payload['custom_machine_limit']) ? (int)$payload['custom_machine_limit'] : null;
    $rawStaff      = isset($payload['custom_staff_limit']) && is_numeric($payload['custom_staff_limit']) ? (int)$payload['custom_staff_limit'] : null;

    $calc          = calculate_tiered_capacity_price($planTier, $rawMach, $rawStaff);
    $planTier      = $calc['tier'];
    $finalMachines = $calc['final_machines'];
    $finalStaff    = $calc['final_staff'];
    $finalPrice    = $calc['total_price'];

    if ($businessName === '') {
        $businessName = $name . ' Vending';
    }

    $licenseKey = generate_license_key($planTier);
    $txnRef     = generate_transaction_ref();
    $hash       = password_hash($password, PASSWORD_BCRYPT);
    $verifyToken = bin2hex(random_bytes(32));

    $pdo->beginTransaction();
    try {
        // 1. Insert operator into users with active tier license automatically attached
        $uIns = $pdo->prepare("
            INSERT INTO users (
                name, first_name, middle_name, last_name, email, password_hash, business_name, plan_tier,
                custom_machine_limit, custom_staff_limit, custom_price,
                license_key, currency, default_threshold, role, status,
                email_verified_at, verification_token, last_login_at
            ) VALUES (
                :name, :fname, :mname, :lname, :email, :hash, :business, :ptier,
                :cmach, :cstaff, :cprice,
                :lic, '$', 10, 'operator', 'active',
                NULL, :vtoken, NULL
            )
        ");
        $uIns->execute([
            ':name'     => $name,
            ':fname'    => $firstName,
            ':mname'    => $middleName,
            ':lname'    => $lastName,
            ':email'    => $email,
            ':hash'     => $hash,
            ':business' => $businessName,
            ':ptier'    => $planTier,
            ':cmach'    => $finalMachines,
            ':cstaff'   => $finalStaff,
            ':cprice'   => $finalPrice,
            ':lic'      => $licenseKey,
            ':vtoken'   => $verifyToken,
        ]);
        $newUserId = (int)$pdo->lastInsertId();

        // 2. Register license key in license_keys table
        $lIns = $pdo->prepare("
            INSERT INTO license_keys (license_key, user_id, plan_name, status, issued_by, activated_at)
            VALUES (:lic, :uid, :pname, 'active', 'self_checkout', NOW())
        ");
        $lIns->execute([
            ':lic'   => $licenseKey,
            ':uid'   => $newUserId,
            ':pname' => $calc['plan_name'],
        ]);

        // 3. Record simulated purchase receipt in billing_transactions
        $bIns = $pdo->prepare("
            INSERT INTO billing_transactions (
                transaction_ref, user_id, license_key, plan_name,
                amount, currency, payment_method, billing_name, status
            ) VALUES (
                :ref, :uid, :lic, :pname,
                :amt, 'USD', :pmethod, :bname, 'paid'
            )
        ");
        $bIns->execute([
            ':ref'     => $txnRef,
            ':uid'     => $newUserId,
            ':lic'     => $licenseKey,
            ':pname'   => $calc['plan_name'],
            ':amt'     => $finalPrice,
            ':pmethod' => $paymentMethod,
            ':bname'   => $name . ' (' . $businessName . ')',
        ]);

        // 4. Optional sample fleet & SKU seed
        if ($seedDemo) {
            seed_sample_inventory_data($pdo, $newUserId);
        }

        // 5. Log registration + license activation in platform_audit_logs
        log_platform_event(
            $pdo,
            $newUserId,
            $name,
            'operator',
            'account_registered',
            "Registered operator workspace '{$businessName}' ({$email}) on {$calc['label']} plan ({$finalMachines} machines, {$finalStaff} staff @ \${$finalPrice}/mo) and auto-activated license {$licenseKey} via {$paymentMethod} ({$txnRef}).",
            $newUserId
        );

        if ($planTier === 'enterprise') {
            log_platform_event(
                $pdo,
                $newUserId,
                $name,
                'operator',
                'enterprise_workspace_provisioned',
                "New Enterprise Workspace activated: '{$businessName}' ({$finalMachines} machines, {$finalStaff} staff @ \${$finalPrice}/mo).",
                $newUserId
            );
        }

        $pdo->commit();

        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute([':id' => $newUserId]);
        $userRow = $uStmt->fetch();

        return [
            'user'               => $userRow,
            'license_key'        => $licenseKey,
            'transaction_ref'    => $txnRef,
            'verification_token' => $verifyToken,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Atomically creates a new Staff account connected to an existing Operator's company via valid active license key
 *
 * @param PDO   $pdo
 * @param array $payload {name, email, password, operator_license_key}
 * @return array {user, operator, verification_token}
 * @throws InvalidArgumentException|Throwable
 */
function provision_staff_account(PDO $pdo, array $payload): array {
    $name       = trim($payload['name'] ?? '');
    $firstName  = isset($payload['first_name']) && trim((string)$payload['first_name']) !== '' ? trim((string)$payload['first_name']) : null;
    $middleName = isset($payload['middle_name']) && trim((string)$payload['middle_name']) !== '' ? trim((string)$payload['middle_name']) : null;
    $lastName   = isset($payload['last_name']) && trim((string)$payload['last_name']) !== '' ? trim((string)$payload['last_name']) : null;
    $email      = strtolower(trim($payload['email'] ?? ''));
    $password   = $payload['password'] ?? '';
    $rawKey     = trim($payload['operator_license_key'] ?? '');
    $cleanKey   = strtoupper(preg_replace('/[^a-zA-Z0-9\-]/', '', $rawKey));

    if ($name === '') {
        throw new InvalidArgumentException("Fill in your first and last name.");
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("Enter a valid email address.");
    }
    if (strlen($password) < 6) {
        throw new InvalidArgumentException("Use a password with at least 6 characters.");
    }
    if ($cleanKey === '') {
        throw new InvalidArgumentException("Please provide your operator's active license key.");
    }

    // 1. Check if email already registered
    $eCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $eCheck->execute([':email' => $email]);
    if ($eCheck->fetch()) {
        throw new InvalidArgumentException("An account with this email address already exists. Sign in instead.");
    }

    // 2. Validate Operator License Key
    // Search license_keys table joined with operator user
    $licStmt = $pdo->prepare("
        SELECT lk.id AS license_row_id, lk.license_key, lk.status AS license_status,
               u.id AS operator_id, u.name AS operator_name, u.business_name AS operator_business,
               u.role AS operator_role, u.status AS operator_status, u.currency AS operator_currency
        FROM license_keys lk
        JOIN users u ON lk.user_id = u.id
        WHERE lk.license_key = :lic AND lk.status = 'active'
        LIMIT 1
    ");
    $licStmt->execute([':lic' => $cleanKey]);
    $operator = $licStmt->fetch();

    if (!$operator) {
        // Fallback: check users table directly in case license was stored directly on operator user record
        $uLicStmt = $pdo->prepare("
            SELECT NULL AS license_row_id, license_key, 'active' AS license_status,
                   id AS operator_id, name AS operator_name, business_name AS operator_business,
                   role AS operator_role, status AS operator_status, currency AS operator_currency
            FROM users
            WHERE license_key = :lic AND role = 'operator'
            LIMIT 1
        ");
        $uLicStmt->execute([':lic' => $cleanKey]);
        $operator = $uLicStmt->fetch();
    }

    if (!$operator) {
        throw new InvalidArgumentException("The license key '{$cleanKey}' is invalid or does not exist. Please check with your operator.");
    }

    if (($operator['operator_status'] ?? '') !== 'active') {
        throw new InvalidArgumentException("The operator associated with this license key is currently suspended or inactive.");
    }

    $operatorId   = (int)$operator['operator_id'];
    $opPlanInfo   = get_workspace_plan_info($pdo, $operatorId);

    $saCntStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_accounts WHERE operator_id = :oid");
    $saCntStmt->execute([':oid' => $operatorId]);
    $uStaffCntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE operator_id = :oid AND role = 'staff'");
    $uStaffCntStmt->execute([':oid' => $operatorId]);
    $currentStaffTotal = (int)$saCntStmt->fetchColumn() + (int)$uStaffCntStmt->fetchColumn();

    if ($currentStaffTotal >= (int)$opPlanInfo['staff_limit']) {
        throw new InvalidArgumentException(
            "This workspace has reached its {$opPlanInfo['label']} license limit of {$opPlanInfo['staff_limit']} staff account(s). Please ask your operator to upgrade their plan or contact admin for extra limits."
        );
    }

    $businessName = trim($operator['operator_business'] ?: ($operator['operator_name'] . ' Vending'));
    $currency     = $operator['operator_currency'] ?: '$';
    $hash         = password_hash($password, PASSWORD_BCRYPT);
    $verifyToken  = bin2hex(random_bytes(32));

    $pdo->beginTransaction();
    try {
        $uIns = $pdo->prepare("
            INSERT INTO users (
                name, first_name, middle_name, last_name, email, password_hash, business_name, plan_tier,
                license_key, currency, default_threshold, role, status,
                operator_id, can_manage_machines, can_manage_prices,
                email_verified_at, verification_token, last_login_at
            ) VALUES (
                :name, :fname, :mname, :lname, :email, :hash, :business, :ptier,
                :lic, :curr, 10, 'staff', 'active',
                :op_id, 0, 0,
                NULL, :vtoken, NULL
            )
        ");
        $uIns->execute([
            ':name'     => $name,
            ':fname'    => $firstName,
            ':mname'    => $middleName,
            ':lname'    => $lastName,
            ':email'    => $email,
            ':hash'     => $hash,
            ':business' => $businessName,
            ':ptier'    => $opPlanInfo['tier'],
            ':lic'      => $cleanKey,
            ':curr'     => $currency,
            ':op_id'    => $operatorId,
            ':vtoken'   => $verifyToken,
        ]);
        $newStaffId = (int)$pdo->lastInsertId();

        // Platform audit log
        log_platform_event(
            $pdo,
            $newStaffId,
            $name,
            'staff',
            'staff_registered',
            "Staff member '{$name}' ({$email}) joined company '{$businessName}' under operator #{$operatorId} via license {$cleanKey}.",
            $operatorId
        );

        $pdo->commit();

        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute([':id' => $newStaffId]);
        $staffUser = $uStmt->fetch();

        return [
            'user'               => $staffUser,
            'operator'           => $operator,
            'verification_token' => $verifyToken,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Ensures existing operator rows have matching license_keys and billing_transactions entries
 */
function sync_existing_operator_licenses(PDO $pdo): void {
    $ops = $pdo->query("SELECT * FROM users WHERE COALESCE(role, 'operator') != 'superadmin'")->fetchAll();

    foreach ($ops as $op) {
        $uid = (int)$op['id'];
        $planInfo = get_user_plan_info($op);
        $lic = trim($op['license_key'] ?? '');
        if ($lic === '') {
            $lic = generate_license_key($planInfo['tier']);
            $pdo->prepare("UPDATE users SET license_key = :lic, plan_tier = :ptier WHERE id = :id")
                ->execute([':lic' => $lic, ':ptier' => $planInfo['tier'], ':id' => $uid]);
        }

        $chkLic = $pdo->prepare("SELECT id FROM license_keys WHERE license_key = :lic LIMIT 1");
        $chkLic->execute([':lic' => $lic]);
        if (!$chkLic->fetch()) {
            $issuedBy = ($op['email'] === 'demo@vendflow.io') ? 'system_seed' : 'self_checkout';
            $pdo->prepare("
                INSERT INTO license_keys (license_key, user_id, plan_name, status, issued_by, activated_at, created_at)
                VALUES (:lic, :uid, :pname, 'active', :iby, :cat, :cat2)
            ")->execute([
                ':lic'   => $lic,
                ':uid'   => $uid,
                ':pname' => $planInfo['plan_name'],
                ':iby'   => $issuedBy,
                ':cat'   => $op['created_at'] ?? date('Y-m-d H:i:s'),
                ':cat2'  => $op['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        $chkTxn = $pdo->prepare("SELECT id FROM billing_transactions WHERE user_id = :uid LIMIT 1");
        $chkTxn->execute([':uid' => $uid]);
        if (!$chkTxn->fetch()) {
            $isDemo = ($op['email'] === 'demo@vendflow.io');
            $pdo->prepare("
                INSERT INTO billing_transactions (
                    transaction_ref, user_id, license_key, plan_name,
                    amount, currency, payment_method, billing_name, status, created_at
                ) VALUES (
                    :ref, :uid, :lic, :pname,
                    :amt, 'USD', :pmethod, :bname, 'paid', :cat
                )
            ")->execute([
                ':ref'     => $isDemo ? 'VF-TXN-DEMO-0001' : generate_transaction_ref(),
                ':uid'     => $uid,
                ':lic'     => $lic,
                ':pname'   => $planInfo['plan_name'],
                ':amt'     => $planInfo['price'],
                ':pmethod' => $isDemo ? 'System Demo Provisioning' : 'Visa •••• 4242 (Simulated)',
                ':bname'   => $op['name'] . (!empty($op['business_name']) ? ' (' . $op['business_name'] . ')' : ''),
                ':cat'     => $op['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }
}

/**
 * Ensures the platform superadmin account exists
 */
function ensure_superadmin_user(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@vendflow.io' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if (!$admin) {
        $ins = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key, currency, default_threshold, role, status, email_verified_at)
            VALUES (:name, :email, :password_hash, :business_name, 'enterprise', :license_key, :currency, :default_threshold, 'superadmin', 'active', NOW())
        ");
        $ins->execute([
            ':name'              => 'VendFlow Superadmin',
            ':email'             => 'admin@vendflow.io',
            ':password_hash'     => password_hash('adminpassword123', PASSWORD_BCRYPT),
            ':business_name'     => 'VendFlow Platform HQ',
            ':license_key'       => 'VF-SUP-MASTER-KEY',
            ':currency'          => '$',
            ':default_threshold' => 10,
        ]);
        $stmt->execute();
        $admin = $stmt->fetch();
    } elseif (($admin['role'] ?? '') !== 'superadmin') {
        $pdo->exec("UPDATE users SET role = 'superadmin' WHERE email = 'admin@vendflow.io'");
        $admin['role'] = 'superadmin';
    }

    return $admin;
}

/**
 * Ensures the 1-click demo operator account exists on the Solo tier
 */
function ensure_demo_user(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'demo@vendflow.io' LIMIT 1");
    $stmt->execute();
    $demoUser = $stmt->fetch();

    if (!$demoUser) {
        $ins = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key, currency, default_threshold, role, status, email_verified_at)
            VALUES (:name, :email, :password_hash, :business_name, 'solo', :license_key, :currency, :default_threshold, 'operator', 'active', NOW())
        ");
        $ins->execute([
            ':name'              => 'Alex Morgan',
            ':email'             => 'demo@vendflow.io',
            ':password_hash'     => password_hash('password123', PASSWORD_BCRYPT),
            ':business_name'     => 'Metro Vending Co.',
            ':license_key'       => 'VF-SOL-DEMO-98A1',
            ':currency'          => '$',
            ':default_threshold' => 10,
        ]);
        $newId = (int)$pdo->lastInsertId();
        $stmt->execute();
        $demoUser = $stmt->fetch();

        $mIns = $pdo->prepare("
            INSERT INTO machines (user_id, name, identifier, location, notes, status)
            VALUES (:uid, :name, :identifier, :location, :notes, 'active')
        ");
        $mIns->execute([
            ':uid'        => $newId,
            ':name'       => 'Machine A — Office Building',
            ':identifier' => 'VM-OFFICE-01',
            ':location'   => '2nd Floor Breakroom, Tech Towers',
            ':notes'      => 'High foot traffic during morning coffee breaks and afternoon rush.',
        ]);
        $mIns->execute([
            ':uid'        => $newId,
            ':name'       => 'Machine B — Campus Hall',
            ':identifier' => 'VM-CAMPUS-02',
            ':location'   => 'Student Union Center, Building C',
            ':notes'      => 'Popular snack spot for students between lectures.',
        ]);
    } else {
        // Keep shared demo account normalized to Solo tier & Solo demo key
        if (($demoUser['plan_tier'] ?? '') !== 'solo' || ($demoUser['license_key'] ?? '') === 'VF-UNL-DEMO-98A1') {
            $demoUid = (int)$demoUser['id'];
            $pdo->prepare("UPDATE users SET plan_tier = 'solo', license_key = 'VF-SOL-DEMO-98A1' WHERE id = :id")
                ->execute([':id' => $demoUid]);
            $pdo->prepare("UPDATE license_keys SET license_key = 'VF-SOL-DEMO-98A1', plan_name = 'VendFlow Solo License' WHERE user_id = :id")
                ->execute([':id' => $demoUid]);
            $pdo->prepare("UPDATE billing_transactions SET license_key = 'VF-SOL-DEMO-98A1', plan_name = 'VendFlow Solo License', amount = 19.00 WHERE user_id = :id")
                ->execute([':id' => $demoUid]);
            $stmt->execute();
            $demoUser = $stmt->fetch();
        }
    }

    return $demoUser;
}

/**
 * Optional helper to load baseline sample machines + 9 SKUs
 */
function seed_sample_inventory_data(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("
        INSERT INTO machines (user_id, name, identifier, location, notes, status)
        VALUES (:user_id, :name, :identifier, :location, :notes, :status)
    ");
    $stmt->execute([
        ':user_id'    => $userId,
        ':name'       => 'Machine A — Office Building',
        ':identifier' => 'VM-OFFICE-01',
        ':location'   => '2nd Floor Breakroom, Tech Towers',
        ':notes'      => 'High foot traffic during morning coffee breaks and afternoon rush.',
        ':status'     => 'active',
    ]);
    $machineAId = (int)$pdo->lastInsertId();

    $stmt->execute([
        ':user_id'    => $userId,
        ':name'       => 'Machine B — Campus Hall',
        ':identifier' => 'VM-CAMPUS-02',
        ':location'   => 'Student Union Center, Building C',
        ':notes'      => 'Popular snack spot for students between lectures.',
        ':status'     => 'active',
    ]);
    $machineBId = (int)$pdo->lastInsertId();

    $productsMachineA = [
        ['name' => 'Potato Chips',  'sku' => 'SNK-CHP-01', 'category' => 'Chips & Snacks',         'quantity' => 12, 'min_threshold' => 10, 'cost_price' => 0.65, 'sell_price' => 1.75, 'image_url' => 'assets/images/products/chips.svg',     'notes' => 'Classic sea salt & vinegar flavor.'],
        ['name' => 'Chocolate Bar', 'sku' => 'SWT-CHO-01', 'category' => 'Candy & Chocolate',      'quantity' => 8,  'min_threshold' => 10, 'cost_price' => 0.80, 'sell_price' => 2.00, 'image_url' => 'assets/images/products/chocolate.svg', 'notes' => 'Milk chocolate hazelnut bar.'],
        ['name' => 'Bottled Water', 'sku' => 'DRK-WTR-01', 'category' => 'Bottled Water & Juices', 'quantity' => 20, 'min_threshold' => 15, 'cost_price' => 0.35, 'sell_price' => 1.25, 'image_url' => 'assets/images/products/water.svg',     'notes' => 'Natural spring water 500ml.'],
        ['name' => 'Cola',          'sku' => 'DRK-SDA-01', 'category' => 'Cold Drinks & Sodas',    'quantity' => 4,  'min_threshold' => 12, 'cost_price' => 0.50, 'sell_price' => 1.50, 'image_url' => 'assets/images/products/cola.svg',      'notes' => '330ml chilled can.'],
        ['name' => 'Biscuits',      'sku' => 'SNK-BSC-01', 'category' => 'Chips & Snacks',         'quantity' => 15, 'min_threshold' => 10, 'cost_price' => 0.55, 'sell_price' => 1.60, 'image_url' => 'assets/images/products/biscuits.svg',  'notes' => 'Oatmeal honey sandwich cookies.'],
    ];

    $productsMachineB = [
        ['name' => 'Potato Chips',  'sku' => 'SNK-CHP-02', 'category' => 'Chips & Snacks',         'quantity' => 6,  'min_threshold' => 10, 'cost_price' => 0.65, 'sell_price' => 1.75, 'image_url' => 'assets/images/products/chips.svg',     'notes' => 'Barbecue flavored potato chips.'],
        ['name' => 'Chocolate Bar', 'sku' => 'SWT-CHO-02', 'category' => 'Candy & Chocolate',      'quantity' => 3,  'min_threshold' => 8,  'cost_price' => 0.80, 'sell_price' => 2.00, 'image_url' => 'assets/images/products/chocolate.svg', 'notes' => 'Dark chocolate wafer bar.'],
        ['name' => 'Bottled Water', 'sku' => 'DRK-WTR-02', 'category' => 'Bottled Water & Juices', 'quantity' => 10, 'min_threshold' => 12, 'cost_price' => 0.35, 'sell_price' => 1.25, 'image_url' => 'assets/images/products/water.svg',     'notes' => 'Purified mineral water.'],
        ['name' => 'Fruit Juice',   'sku' => 'DRK-JCE-01', 'category' => 'Bottled Water & Juices', 'quantity' => 7,  'min_threshold' => 10, 'cost_price' => 0.70, 'sell_price' => 2.20, 'image_url' => 'assets/images/products/juice.svg',     'notes' => '100% Orange Juice 300ml bottle.'],
    ];

    $prodStmt = $pdo->prepare("
        INSERT INTO products (user_id, machine_id, name, sku, category, quantity, min_threshold, cost_price, sell_price, image_url, notes)
        VALUES (:user_id, :machine_id, :name, :sku, :category, :quantity, :min_threshold, :cost_price, :sell_price, :image_url, :notes)
    ");

    $logStmt = $pdo->prepare("
        INSERT INTO inventory_logs (user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes, created_at)
        VALUES (:user_id, :product_id, :machine_id, 0, :new_qty, :change_qty, 'initial', 'Initial system stocking record', DATE_SUB(NOW(), INTERVAL 2 DAY))
    ");

    foreach ($productsMachineA as $p) {
        $prodStmt->execute([
            ':user_id'       => $userId,
            ':machine_id'    => $machineAId,
            ':name'          => $p['name'],
            ':sku'           => $p['sku'],
            ':category'      => $p['category'],
            ':quantity'      => $p['quantity'],
            ':min_threshold' => $p['min_threshold'],
            ':cost_price'    => $p['cost_price'],
            ':sell_price'    => $p['sell_price'],
            ':image_url'     => $p['image_url'],
            ':notes'         => $p['notes'],
        ]);
        $prodId = (int)$pdo->lastInsertId();
        $logStmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $prodId,
            ':machine_id' => $machineAId,
            ':new_qty'    => $p['quantity'],
            ':change_qty' => $p['quantity'],
        ]);
    }

    foreach ($productsMachineB as $p) {
        $prodStmt->execute([
            ':user_id'       => $userId,
            ':machine_id'    => $machineBId,
            ':name'          => $p['name'],
            ':sku'           => $p['sku'],
            ':category'      => $p['category'],
            ':quantity'      => $p['quantity'],
            ':min_threshold' => $p['min_threshold'],
            ':cost_price'    => $p['cost_price'],
            ':sell_price'    => $p['sell_price'],
            ':image_url'     => $p['image_url'],
            ':notes'         => $p['notes'],
        ]);
        $prodId = (int)$pdo->lastInsertId();
        $logStmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $prodId,
            ':machine_id' => $machineBId,
            ':new_qty'    => $p['quantity'],
            ':change_qty' => $p['quantity'],
        ]);
    }
}
