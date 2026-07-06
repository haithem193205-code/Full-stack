<?php
/**
 * Shared <head> + Navbar partial.
 * Expects an optional $activePage variable set by the including page
 * to highlight the correct nav link.
 */
$activePage = $activePage ?? '';
$currentUser = isset($auth) ? $auth->user() : null;
$flashMessage = function_exists('getFlash') ? getFlash() : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> — Your Intelligent Conversation Partner</title>
    <meta name="description" content="A modern AI chat assistant platform built with PHP and vanilla JavaScript.">

    <!-- Fonts (Latin + Arabic) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- App styles -->
    <link rel="stylesheet" href="style.css">

    <!-- Apply the saved language/direction/theme before first paint to avoid a flash -->
    <script>
        (function () {
            var lang = localStorage.getItem('flth_lang') || 'en';
            document.documentElement.lang = lang;
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';

            var theme = localStorage.getItem('flth_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

</head>
<body>

    <!-- ============ Animated Background ============ -->
    <div class="bg-decor" aria-hidden="true">
        <span class="blob blob-1"></span>
        <span class="blob blob-2"></span>
        <span class="blob blob-3"></span>
        <div class="grid-overlay"></div>
    </div>

    <!-- ============ Navbar ============ -->
    <header class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="brand">

<span class="brand-icon">
  <img src="logo.png" alt="Logo" class="brand-icon-img">
</span>
                <span class="brand-text"><?= htmlspecialchars(APP_NAME) ?></span>
            </a>

            <nav class="nav-links" id="navLinks">
                <a href="index.php" class="<?= $activePage === 'home' ? 'active' : '' ?>" data-i18n="nav.home">Home</a>
                <a href="index.php#about" class="<?= $activePage === 'about' ? 'active' : '' ?>" data-i18n="nav.about">About</a>
                <a href="index.php#contact" class="<?= $activePage === 'contact' ? 'active' : '' ?>" data-i18n="nav.contact">Contact</a>

                <!-- Shown only on mobile, inside the hamburger dropdown, so the
                     top bar never has to fit these buttons horizontally. -->
                <div class="nav-links-divider" aria-hidden="true"></div>
                <a href="assistant.php" class="nav-links-mobile-only nav-links-cta">
                    <i class="fa-solid fa-message"></i> <span data-i18n="nav.launchChat">Launch Chat</span>
                </a>
                <?php if ($currentUser): ?>
                    <a href="assistant.php" class="nav-links-mobile-only"><i class="fa-solid fa-comments"></i> <span data-i18n="nav.myChats">My Chats</span></a>
                    <a href="logout.php" class="nav-links-mobile-only"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span data-i18n="nav.logout">Log out</span></a>
                <?php else: ?>
                    <a href="login.php" class="nav-links-mobile-only"><i class="fa-solid fa-right-to-bracket"></i> <span data-i18n="nav.login">Log in</span></a>
                    <a href="register.php" class="nav-links-mobile-only nav-links-cta"><i class="fa-solid fa-user-plus"></i> <span data-i18n="nav.signup">Sign up</span></a>
                <?php endif; ?>
            </nav>

            <div class="navbar-actions">
                <span class="status-pill" id="statusPill">
                    <span class="status-dot"></span>
                    <span data-i18n="nav.aiOnline">AI Online</span>
                </span>

                <!-- Language toggle: switches the whole UI between English and Arabic (RTL). -->
                <button class="lang-toggle" id="langToggle" type="button" title="Switch language / تبديل اللغة">
                    <i class="fa-solid fa-globe"></i>
                    <span id="langToggleLabel">العربية</span>
                </button>

                <!-- Theme toggle: switches between dark and light mode. -->
                <button class="theme-toggle" id="themeToggle" type="button" title="Switch theme">
                    <i class="fa-solid fa-sun"></i>
                </button>

                <!-- Desktop-only buttons; hidden below 720px and replaced by the
                     matching links inside the hamburger dropdown above. -->
                <div class="navbar-actions-primary">
                    <a href="assistant.php" class="btn btn-gradient btn-sm">
                        <i class="fa-solid fa-message"></i>
                        <span data-i18n="nav.launchChat">Launch Chat</span>
                    </a>

                    <?php if ($currentUser): ?>
                        <div class="user-menu" id="userMenu">
                            <button class="user-menu-trigger" id="userMenuTrigger" type="button">
                                <span class="user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($currentUser['name'], 0, 1))) ?></span>
                                <span class="user-menu-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <div class="user-menu-dropdown" id="userMenuDropdown">
                                <div class="user-menu-email"><?= htmlspecialchars($currentUser['email']) ?></div>
                                <a href="assistant.php"><i class="fa-solid fa-comments"></i> <span data-i18n="nav.myChats">My Chats</span></a>
                                <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span data-i18n="nav.logout">Log out</span></a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-ghost btn-sm" data-i18n="nav.login">Log in</a>
                        <a href="register.php" class="btn btn-gradient btn-sm" data-i18n="nav.signup">Sign up</a>
                    <?php endif; ?>
                </div>

                <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <?php if ($flashMessage): ?>
        <div class="flash-banner flash-<?= htmlspecialchars($flashMessage['type']) ?>">
            <?= htmlspecialchars($flashMessage['message']) ?>
        </div>
    <?php endif; ?>