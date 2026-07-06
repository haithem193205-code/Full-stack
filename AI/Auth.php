<?php

declare(strict_types=1);

/**
 * ================================================================
 *  Auth — registration, login, session & password-reset logic
 * ================================================================
 */
final class Auth
{
    private PDO $db;

    public function __construct(array $config)
    {
        $this->db = Database::connection($config);
    }

    // ------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------
    public function register(string $name, string $email, string $password): array
    {
        $name  = trim($name);
        $email = strtolower(trim($email));

        if (mb_strlen($name) < 2) {
            throw new InvalidArgumentException('Please enter your full name.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Please enter a valid email address.');
        }

        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            throw new InvalidArgumentException('An account with this email already exists.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $email, $hash]);

        $userId = (int) $this->db->lastInsertId();

        $this->startSession($userId, $name, $email);

        return ['id' => $userId, 'name' => $name, 'email' => $email];
    }

    // ------------------------------------------------------------
    // Login
    // ------------------------------------------------------------
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Generic message on purpose — never reveal which part was wrong.
            throw new InvalidArgumentException('Incorrect email or password.');
        }

        $this->startSession((int) $user['id'], $user['name'], $user['email']);

        unset($user['password_hash']);
        return $user;
    }

    private function startSession(int $id, string $name, string $email): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $id;
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['_started_at'] = time();
    }

    // ------------------------------------------------------------
    // Logout
    // ------------------------------------------------------------
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // ------------------------------------------------------------
    // Current user / guards
    // ------------------------------------------------------------
    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        return [
            'id'    => (int) $_SESSION['user_id'],
            'name'  => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
        ];
    }

    public function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Redirects browser requests to the login page. */
    public function requireLogin(string $redirectTo = 'login.php'): void
    {
        if (!$this->check()) {
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header("Location: {$redirectTo}?redirect={$return}");
            exit;
        }
    }

    /** Returns a 401 JSON response for API requests. */
    public function requireLoginApi(): void
    {
        if (!$this->check()) {
            jsonResponse(['success' => false, 'error' => 'Please sign in to continue.'], 401);
        }
    }

    // ------------------------------------------------------------
    // Forgot / reset password
    // ------------------------------------------------------------

    /**
     * Creates a reset token for the given email, if the account exists.
     * Returns null silently when the email is unknown — callers should
     * still show a generic "check your inbox" message either way, to
     * avoid leaking which emails are registered.
     */
    public function createPasswordReset(string $email): ?array
    {
        $email = strtolower(trim($email));

        $stmt = $this->db->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // Throttle: max 3 reset requests per hour per user.
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM password_resets
             WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)'
        );
        $stmt->execute([$user['id']]);
        if ((int) $stmt->fetch()['c'] >= 3) {
            throw new RuntimeException('Too many reset requests. Please try again later.');
        }

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, used, created_at)
             VALUES (?, ?, ?, 0, NOW())'
        );
        $stmt->execute([$user['id'], $tokenHash, $expiresAt]);

        return [
            'token'   => $token,
            'user_id' => (int) $user['id'],
            'name'    => $user['name'],
            'email'   => $email,
        ];
    }

    public function verifyResetToken(int $userId, string $token): bool
    {
        $stmt = $this->db->prepare(
            'SELECT token_hash, expires_at, used FROM password_resets
             WHERE user_id = ? ORDER BY id DESC LIMIT 5'
        );
        $stmt->execute([$userId]);

        $tokenHash = hash('sha256', $token);

        foreach ($stmt->fetchAll() as $row) {
            if ((int) $row['used'] === 1) {
                continue;
            }
            if (strtotime($row['expires_at']) < time()) {
                continue;
            }
            if (hash_equals($row['token_hash'], $tokenHash)) {
                return true;
            }
        }

        return false;
    }

    public function resetPassword(int $userId, string $token, string $newPassword): void
    {
        if (mb_strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        if (!$this->verifyResetToken($userId, $token)) {
            throw new InvalidArgumentException('This reset link is invalid or has expired.');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$hash, $userId]);

        $tokenHash = hash('sha256', $token);
        $this->db->prepare(
            'UPDATE password_resets SET used = 1 WHERE user_id = ? AND token_hash = ?'
        )->execute([$userId, $tokenHash]);
    }
}
