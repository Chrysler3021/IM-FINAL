<?php
/**
 * VendFlow - Migration Script from SQLite to MySQL
 *
 * Configured for:
 * Host: localhost:3306
 * User: root
 * Password: (empty)
 * Database: vendflow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqlHost = '127.0.0.1';
$mysqlPort = 3306;
$mysqlUser = 'root';
$mysqlPass = '';
$dbName    = 'vendflow';
$sqliteFile = __DIR__ . '/data/vendflow.sqlite';

echo "=== VendFlow SQLite -> MySQL Migration ===\n";

// 1. Connect to MySQL Server (without selecting database first)
try {
    $serverPdo = new PDO(
        "mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4",
        $mysqlUser,
        $mysqlPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "[OK] Connected to MySQL server at {$mysqlHost}:{$mysqlPort}\n";
} catch (PDOException $e) {
    die("[FAIL] Could not connect to MySQL server: " . $e->getMessage() . "\n");
}

// 2. Create vendflow database if it doesn't exist
try {
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "[OK] Database '{$dbName}' verified / created\n";
} catch (PDOException $e) {
    die("[FAIL] Failed to create database '{$dbName}': " . $e->getMessage() . "\n");
}

// 3. Connect to the vendflow database
try {
    $pdo = new PDO(
        "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$dbName};charset=utf8mb4",
        $mysqlUser,
        $mysqlPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    echo "[OK] Connected to database '{$dbName}'\n";
} catch (PDOException $e) {
    die("[FAIL] Could not connect to database '{$dbName}': " . $e->getMessage() . "\n");
}

// 4. Create Tables with MySQL Schema
echo "Creating tables...\n";

// Temporarily disable foreign key checks during table setup
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        business_name VARCHAR(255) NULL,
        plan_tier VARCHAR(50) DEFAULT 'starter',
        license_key VARCHAR(100) NULL,
        currency VARCHAR(10) DEFAULT '$',
        default_threshold INT DEFAULT 10,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

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

$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operator_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        access_scope VARCHAR(50) DEFAULT 'all',
        rights_json TEXT NULL,
        status VARCHAR(50) DEFAULT 'active',
        last_login_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_operator (operator_id),
        INDEX idx_staff_username (username),
        CONSTRAINT fk_staff_operator FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_machines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NOT NULL,
        machine_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_staff_machine (staff_id, machine_id),
        INDEX idx_sm_staff (staff_id),
        INDEX idx_sm_machine (machine_id),
        CONSTRAINT fk_sm_staff FOREIGN KEY (staff_id) REFERENCES staff_accounts(id) ON DELETE CASCADE,
        CONSTRAINT fk_sm_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

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

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "[OK] MySQL table structures verified\n";

// 5. Migrate Data from SQLite if present
if (file_exists($sqliteFile)) {
    echo "Found SQLite database at: {$sqliteFile}\n";
    try {
        $sqlite = new PDO("sqlite:{$sqliteFile}");
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Check if MySQL already has users
        $existingCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($existingCount > 0) {
            echo "[INFO] MySQL 'users' table already contains {$existingCount} records. Skipping data overwrite to preserve existing records.\n";
        } else {
            echo "Starting data copy from SQLite to MySQL...\n";
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            // Migrate Users
            $users = $sqlite->query("SELECT * FROM users")->fetchAll();
            $userStmt = $pdo->prepare("
                INSERT INTO users (id, name, email, password_hash, business_name, plan_tier, license_key, currency, default_threshold, created_at)
                VALUES (:id, :name, :email, :password_hash, :business_name, :plan_tier, :license_key, :currency, :default_threshold, :created_at)
            ");
            foreach ($users as $u) {
                $userStmt->execute([
                    ':id' => $u['id'],
                    ':name' => $u['name'],
                    ':email' => $u['email'],
                    ':password_hash' => $u['password_hash'],
                    ':business_name' => $u['business_name'] ?? null,
                    ':plan_tier' => $u['plan_tier'] ?? 'starter',
                    ':license_key' => $u['license_key'] ?? null,
                    ':currency' => $u['currency'] ?? '$',
                    ':default_threshold' => $u['default_threshold'] ?? 10,
                    ':created_at' => $u['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            echo "  - Migrated " . count($users) . " users\n";

            // Migrate Machines
            $machines = $sqlite->query("SELECT * FROM machines")->fetchAll();
            $machStmt = $pdo->prepare("
                INSERT INTO machines (id, user_id, name, identifier, location, notes, status, created_at)
                VALUES (:id, :user_id, :name, :identifier, :location, :notes, :status, :created_at)
            ");
            foreach ($machines as $m) {
                $machStmt->execute([
                    ':id' => $m['id'],
                    ':user_id' => $m['user_id'],
                    ':name' => $m['name'],
                    ':identifier' => $m['identifier'] ?? null,
                    ':location' => $m['location'],
                    ':notes' => $m['notes'] ?? null,
                    ':status' => $m['status'] ?? 'active',
                    ':created_at' => $m['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            echo "  - Migrated " . count($machines) . " machines\n";

            // Migrate Products
            $products = $sqlite->query("SELECT * FROM products")->fetchAll();
            $prodStmt = $pdo->prepare("
                INSERT INTO products (id, user_id, machine_id, name, sku, category, quantity, min_threshold, cost_price, sell_price, image_url, notes, created_at, updated_at)
                VALUES (:id, :user_id, :machine_id, :name, :sku, :category, :quantity, :min_threshold, :cost_price, :sell_price, :image_url, :notes, :created_at, :updated_at)
            ");
            foreach ($products as $p) {
                $prodStmt->execute([
                    ':id' => $p['id'],
                    ':user_id' => $p['user_id'],
                    ':machine_id' => $p['machine_id'],
                    ':name' => $p['name'],
                    ':sku' => $p['sku'],
                    ':category' => $p['category'],
                    ':quantity' => $p['quantity'],
                    ':min_threshold' => $p['min_threshold'],
                    ':cost_price' => $p['cost_price'],
                    ':sell_price' => $p['sell_price'],
                    ':image_url' => $p['image_url'] ?? null,
                    ':notes' => $p['notes'] ?? null,
                    ':created_at' => $p['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $p['updated_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            echo "  - Migrated " . count($products) . " products\n";

            // Migrate Inventory Logs
            $logs = $sqlite->query("SELECT * FROM inventory_logs")->fetchAll();
            $logStmt = $pdo->prepare("
                INSERT INTO inventory_logs (id, user_id, product_id, machine_id, previous_qty, new_qty, change_qty, change_type, notes, created_at)
                VALUES (:id, :user_id, :product_id, :machine_id, :previous_qty, :new_qty, :change_qty, :change_type, :notes, :created_at)
            ");
            foreach ($logs as $l) {
                $logStmt->execute([
                    ':id' => $l['id'],
                    ':user_id' => $l['user_id'],
                    ':product_id' => $l['product_id'],
                    ':machine_id' => $l['machine_id'],
                    ':previous_qty' => $l['previous_qty'],
                    ':new_qty' => $l['new_qty'],
                    ':change_qty' => $l['change_qty'],
                    ':change_type' => $l['change_type'],
                    ':notes' => $l['notes'] ?? null,
                    ':created_at' => $l['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
            echo "  - Migrated " . count($logs) . " inventory logs\n";

            // Migrate Password Resets
            $resets = $sqlite->query("SELECT * FROM password_resets")->fetchAll();
            $resetStmt = $pdo->prepare("
                INSERT INTO password_resets (id, email, token, expires_at)
                VALUES (:id, :email, :token, :expires_at)
            ");
            foreach ($resets as $r) {
                $resetStmt->execute([
                    ':id' => $r['id'],
                    ':email' => $r['email'],
                    ':token' => $r['token'],
                    ':expires_at' => $r['expires_at']
                ]);
            }
            echo "  - Migrated " . count($resets) . " password reset records\n";

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            echo "[OK] All data successfully transferred from SQLite to MySQL!\n";
        }
    } catch (PDOException $e) {
        die("[FAIL] Data migration error: " . $e->getMessage() . "\n");
    }
} else {
    echo "[INFO] No existing SQLite file found at {$sqliteFile}. Fresh MySQL database prepared.\n";
}

echo "=== Migration Complete ===\n";
