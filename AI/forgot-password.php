<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($auth->check()) {
    header('Location: assistant.php');
    exit;
}

$errors = [];
$sent = false;
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);

    $old['email'] = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        try {
            $reset = $auth->createPasswordReset($old['email']);

            if ($reset) {
                $link = $config['app']['url'] . '/reset-password.php?uid=' . $reset['user_id'] . '&token=' . $reset['token'];
                $html = renderResetPasswordEmail($reset['name'], $link);

                $mailer = new Mailer($config);
                $mailer->send($reset['email'], $reset['name'], 'Reset your ' . APP_NAME . ' password', $html);
            }

            // Always show the same message, whether or not the account exists.
            $sent = true;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            logAppError('Password reset request failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$activePage = 'forgot-password';
require __DIR__ . '/header.php';
?>

<main class="auth-page">
    <div class="auth-card glass-card">
        <div class="auth-head">
            <span class="eyebrow"><i class="fa-solid fa-key"></i> Reset password</span>
            <h1>Forgot your password?</h1>
            <p>Enter your email and we'll send you a link to reset it.</p>
        </div>

        <?php if ($sent): ?>
            <div class="auth-alert auth-alert-success">
                <p><i class="fa-solid fa-envelope-circle-check"></i>
                    If an account exists for <?= htmlspecialchars($old['email']) ?>, a reset link is on its way.
                    Please check your inbox (and spam folder).
                </p>
            </div>
            <p class="auth-switch"><a href="login.php">Back to log in</a></p>
        <?php else: ?>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $err): ?>
                        <p><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" novalidate>
                <?= csrfField() ?>

                <label class="auth-field">
                    <span>Email address</span>
                    <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required autofocus autocomplete="email">
                </label>

                <button type="submit" class="btn btn-gradient btn-lg auth-submit">
                    Send reset link <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>

            <p class="auth-switch"><a href="login.php">Back to log in</a></p>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
