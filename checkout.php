<?php
/**
 * Checkout & License Provisioning Workflow (Evaluation Prototype)
 */
require_once __DIR__ . '/config/auth.php';
$db = get_db();

$selectedPlanKey = isset($_GET['plan']) && in_array($_GET['plan'], ['starter', 'business', 'enterprise']) 
    ? $_GET['plan'] 
    : 'business';

$planPrices = [
    'starter' => ['name' => 'Starter license', 'price' => 19, 'machines' => '1 vending machine'],
    'business' => ['name' => 'Business license', 'price' => 49, 'machines' => 'Up to 15 vending machines'],
    'enterprise' => ['name' => 'Enterprise license', 'price' => 119, 'machines' => 'Unlimited vending machines']
];
$selectedPlan = $planPrices[$selectedPlanKey];

$currentUser = current_user();
$error = '';
$success = false;

// Handle Checkout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan = $_POST['plan'] ?? 'business';
    if (!array_key_exists($plan, $planPrices)) {
        $plan = 'business';
    }

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your security token expired. Submit the form again to activate your license.';
    } else {
        if ($currentUser && !is_demo_user()) {
            $licenseKey = generate_license_key($plan);
            $stmt = $db->prepare("
                UPDATE users 
                SET plan_tier = :plan, license_key = :lic 
                WHERE id = :id
            ");
            $stmt->execute([
                ':plan' => $plan,
                ':lic' => $licenseKey,
                ':id' => $currentUser['id']
            ]);

            set_flash('success', "License provisioned. Active key: {$licenseKey}");
            header("Location: dashboard.php");
            exit;
        } else {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $business = trim($_POST['business_name'] ?? '');
            $seedDemo = isset($_POST['seed_demo']) ? 1 : 0;

            if (empty($name) || empty($email) || empty($password)) {
                $error = 'Enter your name, email address, and a password to create your operator account.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address such as operator@company.com.';
            } elseif (strlen($password) < 6) {
                $error = 'Use a password with at least 6 characters.';
            } else {
                $check = $db->prepare("SELECT id FROM users WHERE email = :email");
                $check->execute([':email' => $email]);
                if ($check->fetch()) {
                    $error = 'An account with this email already exists. Sign in first to change your license tier.';
                } else {
                    $licenseKey = generate_license_key($plan);
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $ins = $db->prepare("
                        INSERT INTO users (name, email, password_hash, business_name, plan_tier, license_key)
                        VALUES (:name, :email, :hash, :business, :plan, :lic)
                    ");
                    $ins->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':hash' => $hash,
                        ':business' => $business ?: 'My Vending Fleet',
                        ':plan' => $plan,
                        ':lic' => $licenseKey
                    ]);
                    $newUserId = (int)$db->lastInsertId();

                    if ($seedDemo) {
                        seed_sample_inventory_data($db, $newUserId);
                    }

                    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
                    $stmt->execute([':id' => $newUserId]);
                    $userRow = $stmt->fetch();
                    login_user($userRow);

                    set_flash('success', "{$planPrices[$plan]['name']} activated for {$name}. License key: {$licenseKey}");
                    header("Location: dashboard.php");
                    exit;
                }
            }
        }
    }
}

$pageTitle = "License Provisioning";
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-12 lg:py-16 bg-paper flex-1">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="max-w-2xl mb-8 space-y-2 text-left">
            <h1 class="text-enamel" style="font-size: clamp(1.75rem, 2.5vw + 1rem, 2.5rem);">Provision software license</h1>
            <p class="text-sm sm:text-base text-steel leading-relaxed prose-measure">
                Issue a digital license key for your operator account and open your inventory workspace.
            </p>
        </div>

        <!-- Prototype Sandbox Disclosure -->
        <div class="mb-8 p-4 rounded-control bg-amberBg border border-amberBorder text-amberPar text-xs sm:text-sm leading-relaxed">
            <strong>Evaluation sandbox mode:</strong> This provisioning workflow issues a working digital license key in the local database without charging a payment card.
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-control bg-stockoutBg border border-stockoutBorder text-stockout text-sm font-bold" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- Left Column: Account & Activation Form -->
            <div class="lg:col-span-7 bg-tray rounded-frame p-6 sm:p-8 border border-coil">
                <form action="checkout.php?plan=<?= e($selectedPlanKey) ?>" method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="plan" value="<?= e($selectedPlanKey) ?>">

                    <?php if ($currentUser && !is_demo_user()): ?>
                        <div class="pb-4 border-b border-coil space-y-1">
                            <span class="text-xs text-steel block">Signed-in operator</span>
                            <div class="text-base font-bold text-enamel"><?= e($currentUser['name']) ?> (<?= e($currentUser['email']) ?>)</div>
                            <div class="text-xs text-steel">Fleet: <?= e($currentUser['business_name'] ?: 'Not set') ?></div>
                            <div class="text-xs font-bold text-tote pt-1">Current tier: <?= e(ucfirst($currentUser['plan_tier'])) ?></div>
                        </div>
                    <?php else: ?>
                        <?php if (is_demo_user()): ?>
                            <div class="mb-4 p-3.5 rounded-control bg-paper border border-coil text-xs text-steel flex items-start space-x-2.5">
                                <span class="w-2 h-2 rounded-full bg-tote mt-1 flex-shrink-0" aria-hidden="true"></span>
                                <div>
                                    <strong class="text-enamel">Currently evaluating demo workspace:</strong> Enter your operator credentials below to provision your own <strong class="text-enamel"><?= e($selectedPlan['name']) ?></strong> account and exit demo mode.
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="space-y-4">
                            <div>
                                <h2 class="font-display font-bold text-enamel" style="font-size: 1.25rem;">1. Operator credentials</h2>
                                <p class="text-xs text-steel mt-0.5">Use these credentials to sign in to your inventory ledger.</p>
                            </div>

                            <div>
                                <label for="checkout-name" class="block text-sm font-bold text-enamel mb-1">Full name *</label>
                                <input type="text" id="checkout-name" name="name" required placeholder="e.g. Sarah Jenkins" value="<?= e($_POST['name'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                            </div>

                            <div>
                                <label for="checkout-business" class="block text-sm font-bold text-enamel mb-1">Business or route name</label>
                                <input type="text" id="checkout-business" name="business_name" placeholder="e.g. Jenkins Vending Routes" value="<?= e($_POST['business_name'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                            </div>

                            <div>
                                <label for="checkout-email" class="block text-sm font-bold text-enamel mb-1">Email address *</label>
                                <input type="email" id="checkout-email" name="email" required placeholder="sarah@jenkinsvending.com" value="<?= e($_POST['email'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                            </div>

                            <div>
                                <label for="checkout-password" class="block text-sm font-bold text-enamel mb-1">Password *</label>
                                <input type="password" id="checkout-password" name="password" required minlength="6" placeholder="At least 6 characters" class="w-full px-3.5 py-2.5 text-sm rounded-control border border-coil bg-tray text-enamel placeholder-steel/60">
                            </div>

                            <div class="pt-1">
                                <label class="flex items-start space-x-2.5 text-xs text-steel cursor-pointer">
                                    <input type="checkbox" name="seed_demo" value="1" checked class="mt-0.5 w-4 h-4 accent-tote rounded-tag border-coil">
                                    <span>Pre-load sample inventory records for Machine A and Machine B so I can test the ledger right away</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="pt-5 border-t border-coil space-y-3">
                        <div>
                            <h2 class="font-display font-bold text-enamel" style="font-size: 1.25rem;"><?= ($currentUser && !is_demo_user()) ? 'Provisioning mode' : '2. Provisioning mode' ?></h2>
                            <p class="text-xs text-steel mt-0.5">Direct sandbox key generation</p>
                        </div>

                        <div class="py-3 border-t border-b border-coil flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-bold text-enamel">Evaluation license issuer</div>
                                <div class="text-xs text-steel">Generates an active license key with $0.00 charged</div>
                            </div>
                            <span class="px-2 py-0.5 rounded-tag text-xs font-bold bg-toteTint text-tote">Selected</span>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 px-5 rounded-control bg-tote hover:bg-toteHover text-tray font-bold text-sm transition-colors cursor-pointer">
                            Activate <?= e($selectedPlan['name']) ?>
                        </button>
                    </div>

                    <?php if (!$currentUser || is_demo_user()): ?>
                        <p class="text-xs text-steel">
                            Already registered? <a href="login.php" class="text-tote font-bold underline hover:text-toteHover">Sign in to your account</a>
                        </p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Right Column: License Specification Summary -->
            <div class="lg:col-span-5 space-y-5 tabular-nums">

                <div class="bg-tray rounded-frame p-6 border border-coil">
                    <h2 class="font-display font-bold text-enamel pb-3 border-b border-coil" style="font-size: 1.25rem;">License summary</h2>

                    <dl class="mt-4 space-y-2.5 text-sm pb-4 border-b border-coil">
                        <div class="flex items-center justify-between">
                            <dt class="font-bold text-enamel"><?= e($selectedPlan['name']) ?></dt>
                            <dd class="font-bold text-enamel">$<?= (int)$selectedPlan['price'] ?>.00 / mo</dd>
                        </div>
                        <div class="flex items-center justify-between text-xs text-steel">
                            <dt>Machine capacity</dt>
                            <dd class="font-bold text-enamel"><?= e($selectedPlan['machines']) ?></dd>
                        </div>
                        <div class="flex items-center justify-between text-xs text-steel">
                            <dt>Sandbox evaluation charge</dt>
                            <dd class="font-bold text-parGreen">$0.00 today</dd>
                        </div>
                    </dl>

                    <div class="mt-4">
                        <span class="text-xs font-bold text-enamel block mb-2">Change license tier</span>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs">
                            <a href="checkout.php?plan=starter" class="py-2 px-2 rounded-control border <?= $selectedPlanKey === 'starter' ? 'border-tote bg-toteTint font-bold text-tote' : 'border-coil hover:bg-paper text-steel' ?>">Starter</a>
                            <a href="checkout.php?plan=business" class="py-2 px-2 rounded-control border <?= $selectedPlanKey === 'business' ? 'border-tote bg-toteTint font-bold text-tote' : 'border-coil hover:bg-paper text-steel' ?>">Business</a>
                            <a href="checkout.php?plan=enterprise" class="py-2 px-2 rounded-control border <?= $selectedPlanKey === 'enterprise' ? 'border-tote bg-toteTint font-bold text-tote' : 'border-coil hover:bg-paper text-steel' ?>">Enterprise</a>
                        </div>
                    </div>
                </div>

                <div class="bg-enamel text-enamelMuted rounded-frame p-5 border border-enamelBorder text-xs space-y-1.5 leading-relaxed">
                    <div class="font-display font-semibold text-sm text-tray">Software-only scope</div>
                    <p>
                        VendFlow licenses digital recordkeeping software only. Physical vending machines, coin mechanisms, and telemetry sensors are not included.
                    </p>
                </div>

            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
