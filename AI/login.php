<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($auth->check()) {
    header('Location: assistant.php');
    exit;
}

$errors = [];
$old = ['email' => ''];
$redirect = isset($_GET['redirect']) ? (string) $_GET['redirect'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);

    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $password     = (string) ($_POST['password'] ?? '');
    $redirect     = (string) ($_POST['redirect'] ?? '');

    try {
        $auth->login($old['email'], $password);

        $target = ($redirect !== '' && str_starts_with($redirect, '/')) ? $redirect : 'assistant.php';
        header('Location: ' . $target);
        exit;
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        logAppError('Login failed: ' . $e->getMessage());
        $errors[] = 'Something went wrong. Please try again.';
    }
}

$activePage = 'login';
require __DIR__ . '/header.php';
?>

<main class="auth-page">
    <div class="auth-card glass-card">
        <div class="auth-head">
            <span class="eyebrow"><i class="fa-solid fa-right-to-bracket"></i> Welcome back</span>
            <h1>Log in to <?= htmlspecialchars(APP_NAME) ?></h1>
            <p>Pick up your conversations right where you left them.</p>
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
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <label class="auth-field">
                <span>Email address</span>
                <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required autofocus autocomplete="email">
            </label>

            <label class="auth-field">
                <span>Password</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>

            <div class="auth-row">
                <a href="forgot-password.php" class="auth-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-gradient btn-lg auth-submit">
                Log in <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <p class="auth-switch">Don't have an account? <a href="register.php">Sign up</a></p>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
