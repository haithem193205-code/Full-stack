<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($auth->check()) {
    header('Location: assistant.php');
    exit;
}

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);

    $old['name']  = trim((string) ($_POST['name'] ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $password     = (string) ($_POST['password'] ?? '');
    $confirm      = (string) ($_POST['password_confirmation'] ?? '');

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        try {
            $auth->register($old['name'], $old['email'], $password);
            flash('success', 'Welcome! Your account has been created.');
            header('Location: assistant.php');
            exit;
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            logAppError('Registration failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$activePage = 'register';
require __DIR__ . '/header.php';
?>

<main class="auth-page">
    <div class="auth-card glass-card">
        <div class="auth-head">
            <span class="eyebrow"><i class="fa-solid fa-user-plus"></i> Create account</span>
            <h1>Join <?= htmlspecialchars(APP_NAME) ?></h1>
            <p>Create an account to save your conversations and pick up where you left off.</p>
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

            <label class="auth-field">
                <span>Full name</span>
                <input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required autofocus autocomplete="name">
            </label>

            <label class="auth-field">
                <span>Email address</span>
                <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required autocomplete="email">
            </label>

            <label class="auth-field">
                <span>Password</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>

            <label class="auth-field">
                <span>Confirm password</span>
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </label>

            <button type="submit" class="btn btn-gradient btn-lg auth-submit">
                Create account <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
