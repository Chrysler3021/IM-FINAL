<?php
/**
 * Forgot Password Flow
 */
require_once __DIR__ . '/config/auth.php';

$message = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Your session token expired. Submit your email address again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $db = get_db();
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16));
            $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $ins = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :exp)");
            $ins->execute([':email' => $email, ':token' => $token, ':exp' => $exp]);

            $resetLink = "reset-password.php?token=" . $token . "&email=" . urlencode($email);
            $message = "A password reset token has been generated for this local sandbox session.";
        } else {
            $message = "If an operator account matches that email address, a reset link has been prepared.";
        }
    }
}

$pageTitle = "Reset Password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-12 lg:py-16 bg-paper flex-1 flex flex-col justify-center">
    <div class="max-w-md w-full mx-auto px-4 sm:px-6 text-left">

        <div class="mb-6 space-y-1.5">
            <h1 class="text-enamel" style="font-size: 1.875rem;">Reset password</h1>
            <p class="text-sm text-steel">Enter your operator email address to generate a password reset link.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-5 p-4 rounded-control bg-toteTint border border-coil text-enamel text-xs leading-relaxed" role="status">
                <p class="font-bold"><?= e($message) ?></p>
                <?php if ($resetLink): ?>
                    <div class="mt-3 p-3 bg-tray rounded-control border border-coil">
                        <span class="text-steel block mb-1">Local sandbox reset link:</span>
                        <a href="<?= e($resetLink) ?>" class="text-tote font-bold underline break-all">Set a new password now</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="bg-tray p-6 sm:p-7 rounded-frame border border-coil">
            <form action="forgot-password.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div>
                    <label for="reset-email" class="block text-sm font-bold text-enamel mb-1">Account email address</label>
                    <input type="email" id="reset-email" name="email" required placeholder="demo@vendflow.io" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-control bg-tote hover:bg-toteHover text-tray font-bold text-sm transition-colors cursor-pointer">
                        Generate reset link
                    </button>
                </div>
            </form>

            <div class="mt-5 pt-4 border-t border-coil text-xs text-steel">
                Remembered your password?
                <a href="login.php" class="text-tote font-bold underline hover:text-toteHover ml-1">Return to sign in</a>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
