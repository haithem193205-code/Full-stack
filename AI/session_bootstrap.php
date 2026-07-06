<?php

declare(strict_types=1);

/**
 * ================================================================
 *  session_bootstrap.php — starts a hardened PHP session
 * ================================================================
 *  Must be required AFTER config.php (needs the APP_ENV constant).
 * ================================================================
 */

if (session_status() === PHP_SESSION_NONE) {

    $isProduction = defined('APP_ENV') && APP_ENV === 'production';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isProduction, // requires HTTPS in production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('flth_session');
    session_start();

    // Rotate the session id periodically to reduce fixation risk,
    // without breaking the session on every request.
    if (empty($_SESSION['_started_at'])) {
        $_SESSION['_started_at'] = time();
    } elseif (time() - $_SESSION['_started_at'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_started_at'] = time();
    }
}
