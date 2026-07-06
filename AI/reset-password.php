<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$userId = isset($_GET['uid']) ? (int) $_GET['uid'] : (int) ($_POST['uid'] ?? 0);
$token  = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

$errors = [];
$success = false;
$linkValid = $userId > 0 && $token !== '' && $auth->verifyResetToken($userId, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirmation'] ?? '');

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        try {
            $auth->resetPassword($userId, $token, $password);
            $success = true;
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
            $linkValid = false;
        } catch (Throwable $e) {
            logAppError('Password reset failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$activePage = 'reset-password';
require __DIR__ . '/header.php';
?>

<main class="auth-page">
    <div class="auth-card glass-card">

        <?php if ($success): ?>
            <div class="auth-head">
                <span class="eyebrow"><i class="fa-solid fa-circle-check"></i> All set</span>
                <h1>Password updated</h1>
                <p>You can now log in with your new password.</p>
            </div>
            <a href="login.php" class="btn btn-gradient btn-lg auth-submit" style="text-align:center; display:block;">
                Go to log in
            </a>

        <?php elseif (!$linkValid): ?>
            <div class="auth-head">
                <span class="eyebrow"><i class="fa-solid fa-circle-exclamation"></i> Link expired</span>
                <h1>This reset link is invalid</h1>
                <p>It may have already been used or expired. Request a new one below.</p>
            </div>
            <a href="forgot-password.php" class="btn btn-gradient btn-lg auth-submit" style="text-align:center; display:block;">
                Request new link
            </a>

        <?php else: ?>
            <div class="auth-head">
                <span class="eyebrow"><i class="fa-solid fa-lock"></i> New password</span>
                <h1>Choose a new password</h1>
                <p>Make it at least 8 characters long.</p>
            </div>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $err): ?>
                        <p><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="uid" value="<?= (int) $userId ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <label class="auth-field">
                    <span>New password</span>
                    <input type="password" name="password" required minlength="8" autofocus autocomplete="new-password">
                </label>

                <label class="auth-field">
                    <span>Confirm new password</span>
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </label>

                <button type="submit" class="btn btn-gradient btn-lg auth-submit">
                    Update password <i class="fa-solid fa-check"></i>
                </button>
            </form>
        <?php endif; ?>

    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
