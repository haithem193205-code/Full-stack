<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$auth->logout();

header('Location: index.php');
exit;
