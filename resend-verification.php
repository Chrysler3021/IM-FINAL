<?php
/**
 * VendFlow - Resend Verification Email Handler
 */
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';
$prefillEmail = trim($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session security token expired. Please submit again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $db = get_db();
            $stmt = $db->prepare("SELECT id, name, email, email_verified_at, verification_token FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Obscure whether account exists for security, or indicate to check inbox
                $message = 'If an unverified account matches that email address, a new verification link has been sent.';
            } elseif (!empty($user['email_verified_at'])) {
                $error = 'This account is already verified! You can sign in right away.';
            } else {
                $newToken = bin2hex(random_bytes(32));
                $db->prepare("UPDATE users SET verification_token = :token WHERE id = :id")
                   ->execute([':token' => $newToken, ':id' => (int)$user['id']]);

                send_verification_email($user['email'], $user['name'], $newToken);
                $message = "A fresh verification link has been dispatched to {$user['email']}. Please check your Gmail inbox (and Spam folder).";
            }
        }
    }
}

$pageTitle = 'Resend Verification Email';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-shell">
    <div class="auth-card-wrap">

        <div style="margin-bottom: 1.4rem;">
            <h1 class="hero-title" style="font-size: 1.9rem; margin-bottom: 0.35rem;">Resend verification email</h1>
            <p style="font-size: 0.9rem; color: #4b5563;">
                Enter your registered operator email address to receive a fresh verification link.
            </p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="public-alert public-alert-info"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="public-alert public-alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="auth-card">
            <form action="resend-verification.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="public-form-group">
                    <label class="public-label" for="resend-email">Account Email Address *</label>
                    <input
                        type="email"
                        id="resend-email"
                        name="email"
                        required
                        placeholder="operator@company.com"
                        value="<?= e($_POST['email'] ?? $prefillEmail) ?>"
                        class="public-input"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    Send Verification Email &rarr;
                </button>
            </form>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 0.84rem; color: #4b5563; display: flex; justify-content: space-between;">
                <a href="partner.php?window=login" style="color: #f54e00; font-weight: 700;">&larr; Back to Sign In</a>
                <a href="partner.php?window=register" style="color: #4b5563;">Create new account</a>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
