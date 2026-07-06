<?php

/**
 * ================================================================
 *  index.php — Landing Page
 * ================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$activePage = 'home';

require __DIR__ . '/header.php';
?>

<main class="landing">

  <!-- ============ Hero ============ -->
  <section class="hero">
    <div class="hero-content glass-card">

      <!-- Logo -->

      <span class="eyebrow">
        <i class="fa-solid fa-bolt"></i> FLTH AI Assistant
      </span>

      <h1>
        <span data-i18n="home.heroLead">Meet</span> <span class="text-gradient">FLTH AI Assistant <span id="sp">( <span data-i18n="home.heroSub">From Learning To Hiring</span> )</span> </span>
      </h1>

      <p class="hero-subtitle" data-i18n="home.heroDescription">
        A premium, fast, and intelligent chat experience — designed and developed by FLTH for a smooth conversational experience.
      </p>

      <div class="hero-actions">
        <a href="assistant.php" class="btn btn-gradient btn-lg">
          <i class="fa-solid fa-comments"></i>
          <span data-i18n="home.startChatting">Start Chatting</span>
        </a>
        <a href="#about" class="btn btn-ghost btn-lg">
          <span data-i18n="home.learnMore">Learn More</span>
          <i class="fa-solid fa-arrow-down"></i>
        </a>
      </div>

      <div class="hero-stats">
        <div class="stat">
          <strong>100%</strong>
          <span data-i18n="home.statBuilt">FLTH Built</span>
        </div>
        <div class="stat">
          <strong data-i18n="home.statRealtime">Real-Time</strong>
          <span data-i18n="home.statResponses">Responses</span>
        </div>
        <div class="stat">
          <strong>24/7</strong>
          <span data-i18n="home.statAvailability">Availability</span>
        </div>
      </div>
    </div>

    <div class="hero-visual" aria-hidden="true">
      <div class="floating-shape shape-1"></div>
      <div class="floating-shape shape-2"></div>
      <div class="floating-shape shape-3"></div>

      <div class="preview-bubble bubble-ai">
        <i class="fa-solid fa-robot"></i>
        <span data-i18n="home.previewAi">Hello! I’m FLTH AI Assistant.</span>
      </div>

      <div class="preview-bubble bubble-user">
        <span data-i18n="home.previewUser">Let’s build something awesome ✨</span>
      </div>
    </div>
  </section>

  <!-- ============ About / Features ============ -->
  <section class="features" id="about">
    <div class="section-head">
      <span class="eyebrow" data-i18n="home.whyEyebrow">Why choose FLTH AI Assistant</span>
      <h2 data-i18n="home.whyTitle">A smart assistant built for real conversations</h2>
      <p data-i18n="home.whySubtitle">Simple, fast, and designed with a focus on user experience.</p>
    </div>

    <div class="features-grid">

      <div class="glass-card feature-card">
        <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
        <h3 data-i18n="home.featFastTitle">Fast Experience</h3>
        <p data-i18n="home.featFastDesc">Instant responses with smooth, real-time interaction.</p>
      </div>

      <div class="glass-card feature-card">
        <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <h3 data-i18n="home.featSecureTitle">Secure Design</h3>
        <p data-i18n="home.featSecureDesc">Built with safe backend handling and clean input processing.</p>
      </div>

      <div class="glass-card feature-card">
        <div class="feature-icon"><i class="fa-solid fa-brain"></i></div>
        <h3 data-i18n="home.featSmartTitle">Smart AI Logic</h3>
        <p data-i18n="home.featSmartDesc">Designed by FLTH to deliver natural and helpful responses.</p>
      </div>

      <div class="glass-card feature-card">
        <div class="feature-icon"><i class="fa-solid fa-mobile-screen"></i></div>
        <h3 data-i18n="home.featResponsiveTitle">Responsive UI</h3>
        <p data-i18n="home.featResponsiveDesc">Works perfectly across all devices and screen sizes.</p>
      </div>

      <div class="glass-card feature-card">
        <div class="feature-icon"><i class="fa-solid fa-palette"></i></div>
        <h3 data-i18n="home.featDesignTitle">Modern Design</h3>
        <p data-i18n="home.featDesignDesc">Clean glass-style UI with smooth animations.</p>
      </div>

      <div class="glass-card feature-card">
        <div class="feature-icon"><i class="fa-solid fa-code"></i></div>
        <h3 data-i18n="home.featCleanTitle">Clean Architecture</h3>
        <p data-i18n="home.featCleanDesc">Well-structured system built for scalability and control.</p>
      </div>

    </div>
  </section>

  <!-- ============ CTA / Contact ============ -->
  <section class="cta-section" id="contact">
    <div class="glass-card cta-card">
      <h2 data-i18n="home.ctaTitle">Ready to start the conversation?</h2>
      <p data-i18n="home.ctaSubtitle">Jump straight into the FLTH AI Assistant experience.</p>

      <a href="assistant.php" class="btn btn-gradient btn-lg">
        <i class="fa-solid fa-message"></i>
        <span data-i18n="home.ctaButton">Open AI Assistant</span>
      </a>

   <p class="contact-note">
  <span data-i18n="home.contactNote">Developed by FLTH — contact:</span>
  <a href="mailto:haythemmohamed478@gmail.com">Haitham Mohamed</a>
</p>
    </div>
  </section>

</main>

<?php require __DIR__ . '/footer.php'; ?>