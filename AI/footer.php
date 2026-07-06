<!-- ============ Site Footer ============ -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <span class="brand-icon">
                    <img src="logo.png" alt="Logo" class="brand-icon-img">
                </span>
                <span><?= htmlspecialchars(APP_NAME) ?></span>
            </div>

            <p class="footer-tagline" data-i18n="footer.tagline">Modern AI conversation, built for the web.</p>

            <nav class="footer-links">
                <a href="index.php" data-i18n="nav.home">Home</a>
                <a href="index.php#about" data-i18n="nav.about">About</a>
                <a href="index.php#contact" data-i18n="nav.contact">Contact</a>
                <a href="assistant.php" data-i18n="nav.launchChat">Launch Chat</a>
            </nav>

            <p class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- App scripts: lang.js must run first so window.FLTH_I18N exists
         before app.js's t() helper (and the chat UI) needs it. -->
    <script src="lang.js"></script>
    <script src="app.js"></script>
</body>
</html>