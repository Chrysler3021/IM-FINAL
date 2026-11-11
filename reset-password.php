<?php
/**
 * Reset Password Handler
 */
require_once __DIR__ . '/config/auth.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$error = '';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Submit your new password again.';
    } else {
        $token = $_POST['token'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $error = 'Use a password with at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'New password and confirmation password did not match.';
        } else {
            $stmt = $db->prepare("SELECT id FROM password_resets WHERE email = :email AND token = :token AND expires_at > NOW()");
            $stmt->execute([':email' => $email, ':token' => $token]);
            if ($stmt->fetch()) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $up = $db->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
                $up->execute([':hash' => $hash, ':email' => $email]);

                $del = $db->prepare("DELETE FROM password_resets WHERE email = :email");
                $del->execute([':email' => $email]);

                set_flash('success', 'Password updated. Sign in with your new password.');
                header("Location: login.php");
                exit;
            } else {
                $error = 'This reset link is invalid or has expired. Generate a fresh link from the password reset page.';
            }
        }
    }
}

$pageTitle = "Set New Password";
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-12 lg:py-16 bg-paper flex-1 flex flex-col justify-center">
    <div class="max-w-md w-full mx-auto px-4 sm:px-6 text-left">

        <div class="mb-6 space-y-1.5">
            <h1 class="text-enamel" style="font-size: 1.875rem;">Choose new password</h1>
            <p class="text-sm text-steel">Enter and confirm a new password for your operator account.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3.5 rounded-control bg-stockoutBg border border-stockoutBorder text-stockout text-xs font-bold" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-tray p-6 sm:p-7 rounded-frame border border-coil">
            <form action="reset-password.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="email" value="<?= e($email) ?>">

                <div>
                    <label for="new-pass" class="block text-sm font-bold text-enamel mb-1">New password *</label>
                    <input type="password" id="new-pass" name="password" required minlength="6" placeholder="At least 6 characters" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                </div>

                <div>
                    <label for="confirm-pass" class="block text-sm font-bold text-enamel mb-1">Confirm new password *</label>
                    <input type="password" id="confirm-pass" name="confirm_password" required minlength="6" placeholder="Repeat password" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-control bg-tote hover:bg-toteHover text-tray font-bold text-sm transition-colors cursor-pointer">
                        Save new password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
