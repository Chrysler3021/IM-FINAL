<?php
/**
 * Standalone Database Seeder Script
 * Can be run via CLI (`php seed.php`) or web browser
 */
require_once __DIR__ . '/config/db.php';

$pdo = get_db();

echo "Initializing VendFlow database...\n";
initialize_schema($pdo);

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'demo@vendflow.io'");
$stmt->execute();
$demoUser = $stmt->fetch();

if (!$demoUser) {
    $userId = seed_default_demo_user($pdo);
    echo "Created default demo user (demo@vendflow.io / password123) with ID {$userId}.\n";
} else {
    $userId = $demoUser['id'];
    echo "Demo user already exists (ID: {$userId}).\n";
}

$machineCount = $pdo->query("SELECT COUNT(*) FROM machines WHERE user_id = {$userId}")->fetchColumn();
$productCount = $pdo->query("SELECT COUNT(*) FROM products WHERE user_id = {$userId}")->fetchColumn();

echo "Database status:\n";
echo "- Machines: {$machineCount}\n";
echo "- Products: {$productCount}\n";
echo "Demo credentials: demo@vendflow.io / password123\n";
echo "Done!\n";
