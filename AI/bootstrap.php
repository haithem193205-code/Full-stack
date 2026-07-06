<?php

declare(strict_types=1);

/**
 * ================================================================
 *  bootstrap.php — loaded first by every entry point
 * ================================================================
 *  Provides: $config, $auth (Auth), and all core classes.
 *  Usage in a page:
 *      require __DIR__ . '/bootstrap.php';
 * ================================================================
 */

$config = require __DIR__ . '/config.php';

require __DIR__ . '/functions.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/ChatRepository.php';
require __DIR__ . '/session_bootstrap.php';

$auth = new Auth($config);
