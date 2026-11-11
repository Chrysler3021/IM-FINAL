<?php
/**
 * VendFlow - Operator, Staff & Superadmin Sign-In + 1-Click Instant Demo Login
 * Processes authentication & renders inside the PostHog WebOS Sign In Modal Window
 */
require_once __DIR__ . '/includes/functions.php';

// Handle 1-Click Instant Superadmin Demo Login
if (isset($_GET['demo_admin']) && $_GET['demo_admin'] === '1') {
    unset($_SESSION['impersonator_id']);
    $admin = login_demo_superadmin();
    set_flash('success', "Signed into Superadmin console as {$admin['name']}.");
    redirect('superadmin.php');
}

// Handle 1-Click Instant Operator Demo Login
if (isset($_GET['demo']) && $_GET['demo'] === '1') {
    unset($_SESSION['impersonator_id']);
    $demoUser = login_demo_user();
    set_flash('success', "Signed into demo workspace as {$demoUser['name']}.");
    redirect('dashboard.php');
}

// Handle 1-Click Instant Staff Demo Login
if (isset($_GET['demo_staff']) && $_GET['demo_staff'] === '1') {
    $db = get_db();
    $staffStmt = $db->prepare("SELECT * FROM staff_accounts WHERE LOWER(username) = 'dave_route1' LIMIT 1");
    $staffStmt->execute();
    $demoStaff = $staffStmt->fetch();
    if ($demoStaff) {
        $opStmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $opStmt->execute([':id' => (int)$demoStaff['operator_id']]);
        $operator = $opStmt->fetch();
        if ($operator) {
            unset($_SESSION['impersonator_id']);
            login_staff($demoStaff, $operator);
            set_flash('success', "Signed into Route Restocker Portal as {$demoStaff['name']} (@{$demoStaff['username']}).");
            redirect('staff-dashboard.php');
        }
    }
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $loginError = 'Your session security token expired. Please submit again.';
    } else {
        $rawIdent = trim($_POST['email'] ?? $_POST['login_identity'] ?? '');
        $cleanIdent = ltrim($rawIdent, '@');
        $password = $_POST['password'] ?? '';

        if ($cleanIdent === '' || $password === '') {
            $loginError = 'Enter both your email address (or staff username) and password.';
        } else {
            // 1. First check if it matches an Operator / Superadmin by email
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
            $stmt->execute([':email' => $cleanIdent]);
            $userRow = $stmt->fetch();

            if ($userRow && password_verify($password, $userRow['password_hash'])) {
                // Email Verification Check: ONLY verified email accounts can access the platform
                if (empty($userRow['email_verified_at']) && ($userRow['role'] ?? '') !== 'superadmin') {
                    $resendLink = 'resend-verification.php?email=' . urlencode($userRow['email']);
                    $loginError = 'Please verify your email address before logging in. We sent a verification link to your Gmail. <a href="' . e($resendLink) . '" style="color:#f54e00;font-weight:700;text-decoration:underline;margin-left:4px;">Resend email &rarr;</a>';
                } elseif (($userRow['status'] ?? 'active') === 'suspended' && ($userRow['role'] ?? '') !== 'superadmin') {
                    $loginError = 'This operator workspace is currently suspended. Please contact platform support.';
                } else {
                    // Cleanly rotate/wipe any previous session
                    logout_user();
                    start_session_safe();

                    unset($_SESSION['impersonator_id']);
                    $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")
                       ->execute([':id' => (int)$userRow['id']]);
                    log_platform_event(
                        $db,
                        (int)$userRow['id'],
                        $userRow['name'],
                        $userRow['role'] ?? 'operator',
                        'user_login',
                        "Signed in to " . (($userRow['role'] ?? '') === 'superadmin' ? 'Platform Superadmin Console' : 'Operator Workspace') . " ({$userRow['email']}).",
                        (int)$userRow['id']
                    );
                    login_user($userRow);
                    set_flash('success', "Signed in as {$userRow['name']}.");

                    if (($userRow['role'] ?? '') === 'superadmin') {
                        redirect('superadmin.php');
                    }

                    $allowedRedirects = ['dashboard.php', 'inventory.php', 'machines.php', 'low-stock.php', 'audit-log.php', 'settings.php', 'staff.php'];
                    $target = basename($_GET['redirect'] ?? 'dashboard.php');
                    redirect(in_array($target, $allowedRedirects, true) ? $target : 'dashboard.php');
                }
            } else {
                // 2. Next check if it matches a Staff Account by unique username
                $staffStmt = $db->prepare("SELECT * FROM staff_accounts WHERE LOWER(username) = LOWER(:u) LIMIT 1");
                $staffStmt->execute([':u' => $cleanIdent]);
                $staffRow = $staffStmt->fetch();

                if ($staffRow && password_verify($password, $staffRow['password_hash'])) {
                    if (($staffRow['status'] ?? 'active') !== 'active') {
                        $loginError = 'This staff account has been suspended by your vending operator. Please contact them for access.';
                    } else {
                        // Find parent operator
                        $opStmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
                        $opStmt->execute([':id' => (int)$staffRow['operator_id']]);
                        $operatorRow = $opStmt->fetch();

                        if (!$operatorRow) {
                            $loginError = 'The operator account associated with this staff profile was not found.';
                        } elseif (($operatorRow['status'] ?? 'active') === 'suspended') {
                            $loginError = 'The operator workspace for this account is currently suspended.';
                        } else {
                            logout_user();
                            start_session_safe();
                            unset($_SESSION['impersonator_id']);
                            $db->prepare("UPDATE staff_accounts SET last_login_at = NOW() WHERE id = :id")
                               ->execute([':id' => (int)$staffRow['id']]);
                            log_platform_event(
                                $db,
                                (int)$operatorRow['id'],
                                $staffRow['name'],
                                'staff',
                                'staff_login',
                                "Staff member '{$staffRow['name']}' (@{$staffRow['username']}) signed in to Route Restock Portal.",
                                (int)$operatorRow['id']
                            );
                            login_staff($staffRow, $operatorRow);
                            set_flash('success', "Signed in as {$staffRow['name']} (Staff Restocker).");
                            redirect('staff-dashboard.php');
                        }
                    }
                } else {
                    $loginError = 'Invalid email/username or password. Staff accounts sign in using their assigned username.';
                }
            }
        }
    }
} else {
    // Only auto-redirect on GET if already logged in and no specific window requested
    if (is_logged_in() && !isset($_GET['window'])) {
        if (is_staff()) {
            redirect('staff-dashboard.php');
        }
        if (is_superadmin() && !isset($_SESSION['impersonator_id'])) {
            redirect('superadmin.php');
        }
        redirect('dashboard.php');
    }
}

$initialWindow = 'login';
require_once __DIR__ . '/partner.php';
