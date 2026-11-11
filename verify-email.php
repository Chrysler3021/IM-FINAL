<?php
/**
 * VendFlow - Email Address Verification Handler
 * Validates cryptographic verification token and activates the operator account
 */
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');
$success = false;
$errorMessage = '';
$userRow = null;

if ($token === '') {
    $errorMessage = 'No verification token was provided. Please check the link in your email.';
} else {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE verification_token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $userRow = $stmt->fetch();

    if (!$userRow) {
        $errorMessage = 'This verification link is invalid or has already been used. Please try logging in.';
    } else {
        $db->prepare("
            UPDATE users
            SET email_verified_at = NOW(),
                verification_token = NULL
            WHERE id = :id
        ")->execute([':id' => (int)$userRow['id']]);

        log_platform_event(
            $db,
            (int)$userRow['id'],
            $userRow['name'],
            $userRow['role'] ?? 'operator',
            'email_verified',
            "Email verified successfully for {$userRow['email']}.",
            (int)$userRow['id']
        );

        $success = true;
        set_flash('success', "Email verified successfully! You can now log in to your {$userRow['name']} workspace.");
    }
}

$pageTitle = 'Verify Email Address';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-shell">
    <div class="auth-card-wrap">

        <div style="margin-bottom: 1.4rem; text-align: center;">
            <div style="width: 48px; height: 48px; margin: 0 auto 0.5rem; color: <?= $success ? '#16a34a' : '#ea580c' ?>;"><?= posthog_icon($success ? 'check_circle' : 'warning') ?></div>
            <h1 class="hero-title" style="font-size: 1.85rem; margin-bottom: 0.35rem;">
                <?= $success ? 'Email Verified Successfully!' : 'Verification Issue' ?>
            </h1>
            <p style="font-size: 0.92rem; color: #4b5563;">
                <?= $success
                    ? 'Your operator account is active and verified. You may now sign in with your credentials.'
                    : e($errorMessage) ?>
            </p>
        </div>

        <div class="auth-card" style="text-align: center;">
            <?php if ($success): ?>
                <a href="partner.php?window=login" class="btn btn-primary btn-lg" style="width: 100%; display: inline-block;">
                    Sign in to VendFlow &rarr;
                </a>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="partner.php?window=login" class="btn btn-dark" style="width: 100%;">
                        Go to Sign In
                    </a>
                    <a href="resend-verification.php" class="btn btn-secondary" style="width: 100%;">
                        Resend Verification Email &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
