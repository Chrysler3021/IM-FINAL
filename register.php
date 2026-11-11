<?php
/**
 * VendFlow - Create Operator Account & Simulated License Purchase
 * Atomically provisions operator workspace + active Unlimited license key + billing receipt
 */
require_once __DIR__ . '/includes/functions.php';

$registerError = '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax']);

$allowedPaymentMethods = [
    'Visa •••• 4242 (Simulated)',
    'Mastercard •••• 8888 (Simulated)',
    'GCash / Digital Wallet (Sandbox)',
    'Corporate Invoice / Bank Transfer (Sandbox)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $registerError = 'Your session security token expired. Please submit again.';
    } else {
        $accountType     = 'operator';
        $firstName       = trim($_POST['first_name'] ?? '');
        $middleName      = trim($_POST['middle_name'] ?? '');
        $lastName        = trim($_POST['last_name'] ?? '');
        $hasSplitName    = isset($_POST['first_name']) || isset($_POST['last_name']);
        if ($hasSplitName) {
            $nameParts = array_values(array_filter([$firstName, $middleName, $lastName], static fn(string $part): bool => $part !== ''));
            $name = implode(' ', $nameParts);
        } else {
            $name = trim($_POST['name'] ?? '');
        }
        $email           = strtolower(trim($_POST['email'] ?? ''));
        $business        = trim($_POST['business_name'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $paymentMethod   = trim($_POST['payment_method'] ?? $allowedPaymentMethods[0]);
        $seedDemo        = isset($_POST['seed_demo']) ? 1 : 0;
        $planTier        = trim($_POST['plan_tier'] ?? 'solo');
        $customMachines  = (isset($_POST['custom_machine_limit']) && is_numeric($_POST['custom_machine_limit'])) ? (int)$_POST['custom_machine_limit'] : null;
        $customStaff     = (isset($_POST['custom_staff_limit']) && is_numeric($_POST['custom_staff_limit'])) ? (int)$_POST['custom_staff_limit'] : null;

        if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
            $paymentMethod = $allowedPaymentMethods[0];
        }

        if ($hasSplitName && ($firstName === '' || $lastName === '' || $email === '' || $password === '')) {
            $registerError = 'Fill in your first name, last name, email address, and password.';
        } elseif (!$hasSplitName && ($name === '' || $email === '' || $password === '')) {
            $registerError = 'Fill in your full name, email address, and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $registerError = 'Enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $registerError = 'Use a password with at least 6 characters.';
        } elseif ($password !== $confirmPassword) {
            $registerError = 'Password and confirmation password did not match.';
        } else {
            $chk = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $email]);
            if ($chk->fetch()) {
                $registerError = 'An account with this email address already exists. Sign in instead.';
            } else {
                try {
                    // Atomically provision operator workspace and active Unlimited license
                    $provisioned = provision_operator_account($db, [
                        'name'           => $name,
                        'first_name'     => $firstName !== '' ? $firstName : null,
                        'middle_name'    => $middleName !== '' ? $middleName : null,
                        'last_name'      => $lastName !== '' ? $lastName : null,
                        'email'          => $email,
                        'password'       => $password,
                        'business_name'  => $business,
                        'payment_method' => $paymentMethod,
                        'seed_demo'            => $seedDemo,
                        'plan_tier'            => $planTier,
                        'custom_machine_limit' => $customMachines,
                        'custom_staff_limit'   => $customStaff,
                    ]);

                    // Send verification email via Gmail SMTP
                    $mailSent = send_verification_email($email, $name, $provisioned['verification_token']);

                    // Clear any existing session (e.g. Superadmin or demo operator)
                    logout_user();
                    start_session_safe();

                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success'   => true,
                            'email'     => $email,
                            'name'      => $name,
                            'role'      => 'operator',
                            'mail_sent' => (bool)$mailSent,
                            'message'   => 'Email was sent to your account',
                        ]);
                        exit;
                    }

                    if ($mailSent) {
                        set_flash(
                            'success',
                            "Account created! We sent an activation link to <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong>. Please check your inbox and click the link to verify your email before logging in."
                        );
                    } else {
                        set_flash(
                            'warning',
                            "Account created, but we couldn't send the verification email immediately. Please use <a href='resend-verification.php?email=" . urlencode($email) . "'>Resend Verification</a>."
                        );
                    }

                    // Open the "Email was sent to your account" modal window
                    redirect('partner.php?window=email_sent&email=' . urlencode($email));
                } catch (InvalidArgumentException $invEx) {
                    $registerError = $invEx->getMessage();
                } catch (Throwable $e) {
                    error_log('Registration error: ' . $e->getMessage());
                    $registerError = 'Could not complete account provisioning. Please try again.';
                }
            }
        }
    }

    if ($isAjax && $registerError !== '') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $registerError,
        ]);
        exit;
    }
}

$initialWindow = 'register';
require_once __DIR__ . '/partner.php';
