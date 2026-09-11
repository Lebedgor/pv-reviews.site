<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $meta['title'] ?: t('index.meta.title') ?></title>
  <meta name="description" content="<?= $meta['description'] ?: t('index.meta.description') ?>">
  <link rel="canonical" href="<?= getPageUrl($currentLang, $currentPage) ?>">
  <meta name="robots" content="<?= ($currentPage === 'thank-you' || $currentPage === '404') ? 'noindex, nofollow' : 'index, follow' ?>">

  <link rel="icon" href="/assets/favicons/favicon.ico" sizes="48x48">
  <link rel="icon" href="/assets/favicons/favicon-32x32.png" type="image/png" sizes="32x32">
  <link rel="icon" href="/assets/favicons/favicon-16x16.png" type="image/png" sizes="16x16">
  <link rel="apple-touch-icon" href="/assets/favicons/apple-touch-icon.png" sizes="180x180">
  <link rel="manifest" href="/assets/favicons/site.webmanifest">
  <meta name="theme-color" content="#4f46e5">

  <meta property="og:title" content="<?= $meta['title'] ?: t('index.meta.title') ?>">
  <meta property="og:description" content="<?= $meta['description'] ?: t('index.meta.description') ?>">
  <meta property="og:image" content="<?= $siteUrl . ($meta['og_image'] ?: '/assets/main_wide_banner.jpg') ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <?php
  $ogLocales = ['en' => 'en_US', 'ru' => 'ru_RU', 'es' => 'es_ES', 'fr' => 'fr_FR'];
  $isArticle = strpos($pageKey, 'blog_') === 0;
  ?>
  <meta property="og:type" content="<?= $isArticle ? 'article' : 'website' ?>">
  <meta property="og:url" content="<?= getPageUrl($currentLang, $currentPage) ?>">
  <meta property="og:locale" content="<?= $ogLocales[$currentLang] ?? 'en_US' ?>">
  <?php foreach ($ogLocales as $code => $locale): ?>
  <?php if ($code !== $currentLang): ?><meta property="og:locale:alternate" content="<?= $locale ?>"><?php endif; ?>
  <?php endforeach; ?>
  <meta property="og:site_name" content="PVR Media Reviews">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $meta['title'] ?: t('index.meta.title') ?>">
  <meta name="twitter:description" content="<?= $meta['description'] ?: t('index.meta.description') ?>">
  <meta name="twitter:image" content="<?= $siteUrl ?>/assets/main_wide_banner.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
  <link rel="stylesheet" href="/assets/styles.css">

  <?= $metaJsonLd ?? '' ?>

  <!-- Google tag -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-SVBX2VGG58"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-SVBX2VGG58');
  </script>

  <?= getAlternateLinks($currentPage) ?>
</head>
<body class="<?php echo !empty($breadcrumbs) ? 'has-breadcrumbs' : ''; ?>">

  <!-- NAV -->
  <nav class="nav" id="nav">
    <div class="container nav__inner">
      <a href="<?php echo navUrl($currentLang, ""); ?>" class="nav__logo">
        <img src="/assets/logo_text_3d_767.webp" alt="PVR Reviews" class="nav__logo-img">
      </a>
      <div class="nav__links">
        <a href="<?php echo navUrl($currentLang, "#article"); ?>"><?= t('index.nav.features') ?></a>
        <a href="<?php echo navUrl($currentLang, "#pvr-get"); ?>"><?= t('index.nav.why_pvr') ?></a>
        <a href="<?php echo navUrl($currentLang, "#pricing"); ?>"><?= t('index.nav.pricing') ?></a>
        <a href="<?php echo navUrl($currentLang, "#faq"); ?>">FAQ</a>
        <a href="<?php echo navUrl($currentLang, "features"); ?>"><?= t('index.nav.all_features') ?></a>
        <a href="<?php echo navUrl($currentLang, "settings"); ?>"><?= t('index.nav.settings') ?></a>
        <a href="<?php echo navUrl($currentLang, "blog"); ?>"><?= t('index.nav.blog') ?></a>
      </div>
      <div class="nav__overflow">
        <button class="nav__overflow-btn" aria-expanded="false" aria-haspopup="true"></button>
        <div class="nav__overflow-dropdown"></div>
      </div>
      <div class="lang-switch">
        <button class="lang-switch__btn"><?= strtoupper($currentLang) ?> ▾</button>
        <div class="lang-switch__dropdown">
          <?php foreach (['en' => 'English', 'ru' => 'Русский', 'es' => 'Español', 'fr' => 'Français'] as $code => $name): ?>
            <a href="<?= getPageUrl($code, $currentPage) ?>" class="lang-switch__option<?= $code === $currentLang ? ' lang-switch__option--active' : '' ?>"><?= $name ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="nav__mobile-toggle" id="mobileToggle" aria-label="<?= t('index.nav.mobile_aria') ?>" aria-expanded="false" aria-controls="mobileMenu">
        <span></span><span></span><span></span>
      </button>
    </div>
    <div class="nav__mobile-menu" id="mobileMenu">
      <a href="<?php echo navUrl($currentLang, "#article"); ?>"><?= t('index.nav.features') ?></a>
      <a href="<?php echo navUrl($currentLang, "#pvr-get"); ?>"><?= t('index.nav.why_pvr') ?></a>
      <a href="<?php echo navUrl($currentLang, "#pricing"); ?>"><?= t('index.nav.pricing') ?></a>
      <a href="<?php echo navUrl($currentLang, "#faq"); ?>">FAQ</a>
      <a href="<?php echo navUrl($currentLang, "features"); ?>"><?= t('index.nav.all_features') ?></a>
      <a href="<?php echo navUrl($currentLang, "settings"); ?>"><?= t('index.nav.settings') ?></a>
      <a href="<?php echo navUrl($currentLang, "blog"); ?>"><?= t('index.nav.blog') ?></a>
      <div class="lang-switch">
        <button class="lang-switch__btn"><?= strtoupper($currentLang) ?> ▾</button>
        <div class="lang-switch__dropdown">
          <?php foreach (['en' => 'English', 'ru' => 'Русский', 'es' => 'Español', 'fr' => 'Français'] as $code => $name): ?>
            <a href="<?= getPageUrl($code, $currentPage) ?>" class="lang-switch__option<?= $code === $currentLang ? ' lang-switch__option--active' : '' ?>"><?= $name ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </nav>

  <?php if (!empty($breadcrumbs)): ?>
  <!-- BREADCRUMBS -->
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <div class="container">
      <ol class="breadcrumbs__list">
        <?php $last = count($breadcrumbs) - 1; foreach ($breadcrumbs as $i => $crumb): ?>
          <?php if ($i < $last): ?>
            <li><a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['label']); ?></a></li>
          <?php else: ?>
            <li aria-current="page"><?php echo htmlspecialchars($crumb['label']); ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </div>
  </nav>
  <?php endif; ?>

  <!-- PAGE CONTENT -->
  <?php include $templateFile; ?>

  <!-- CHECKOUT MODAL -->
  <div class="checkout-modal" id="checkoutModal" aria-hidden="true">
    <div class="checkout-modal__backdrop" id="checkoutBackdrop"></div>
    <div class="checkout-modal__content" role="dialog" aria-modal="true" aria-labelledby="checkoutTitle">
      <button class="checkout-modal__close" id="checkoutClose" type="button" aria-label="Close">&times;</button>
      <div class="checkout-modal__header">
        <div class="checkout-modal__icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </div>
        <h2 class="checkout-modal__title" id="checkoutTitle"><?= t('index.checkout.title', 'Enter your website URL') ?></h2>
        <p class="checkout-modal__subtitle"><?= t('index.checkout.subtitle', 'PVR Pro is a single-site license. Enter the website where you will install the plugin.') ?></p>
      </div>
      <form class="checkout-modal__form" id="checkoutForm">
        <div class="checkout-modal__field">
          <label class="checkout-modal__label" for="websiteUrl">Website URL</label>
          <input class="checkout-modal__input" id="websiteUrl" name="website_url" type="text" placeholder="example.com" autocomplete="url" required>
          <span class="checkout-modal__hint">Enter the domain where PVR Pro will be installed.</span>
          <span class="checkout-modal__error" id="urlError" role="alert"></span>
        </div>
        <div class="checkout-modal__field">
          <label class="checkout-modal__label" for="customerEmail">Email (optional)</label>
          <input class="checkout-modal__input" id="customerEmail" name="email" type="email" placeholder="you@example.com" autocomplete="email">
          <span class="checkout-modal__error" id="emailError" role="alert"></span>
        </div>
        <button class="btn btn--primary checkout-modal__submit" id="checkoutSubmit" type="submit">
          <span class="checkout-modal__submit-text">Continue to payment</span>
          <span class="checkout-modal__submit-loading" style="display:none;"><span class="checkout-modal__spinner">&#8635;</span> Processing...</span>
        </button>
      </form>
      <div class="checkout-modal__security" aria-hidden="true">Secure payment via our payment provider</div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container footer__inner" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
      <div>&copy; 2026 PVR Media Reviews. <?= t('index.footer.rights') ?></div>
      <div style="display:flex; gap:16px; font-size:14px;">
        <a href="<?php echo navUrl($currentLang, "features"); ?>" style="color:var(--text-muted); text-decoration:none;"><?= t('index.footer.features') ?></a>
        <a href="<?php echo navUrl($currentLang, "settings"); ?>" style="color:var(--text-muted); text-decoration:none;"><?= t('index.footer.settings') ?></a>
        <a href="<?php echo navUrl($currentLang, "blog"); ?>" style="color:var(--text-muted); text-decoration:none;"><?= t('index.footer.blog') ?></a>
        <a href="https://wordpress.org/plugins/pvr-media-reviews-for-woocommerce/" target="_blank" rel="noopener noreferrer" style="color:var(--text-muted); text-decoration:none;">WordPress.org</a>
      </div>
    </div>
    <div class="container footer__disclaimer-container" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle); font-size: 11.5px; color: var(--text-tertiary); line-height: 1.6; text-align: center;">
      <?= t('index.footer.disclaimer') ?>
    </div>
  </footer>

  <!-- SCRIPTS -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    AOS.init({ once: true, duration: 700 });

    // Nav scroll
    var nav = document.getElementById('nav');
    window.addEventListener('scroll', function() {
      nav.classList.toggle('scrolled', window.scrollY > 20);
    });

    // Mobile toggle
    var mobileToggle = document.getElementById('mobileToggle');
    var mobileMenu = document.getElementById('mobileMenu');
    if (mobileToggle && mobileMenu) {
      mobileToggle.addEventListener('click', function() {
        var isOpen = mobileToggle.classList.toggle('active');
        mobileMenu.classList.toggle('open', isOpen);
        mobileToggle.setAttribute('aria-expanded', String(isOpen));
      });
      mobileMenu.querySelectorAll('a').forEach(function(a) {
        a.addEventListener('click', function() {
           mobileToggle.classList.remove('active');
           mobileMenu.classList.remove('open');
           mobileToggle.setAttribute('aria-expanded', 'false');
        });
      });
      // Toggle lang-switch dropdown inside mobile menu
      mobileMenu.querySelectorAll('.lang-switch__btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          btn.closest('.lang-switch').classList.toggle('open');
        });
      });
    }

    // Checkout modal
    var checkoutModal = document.getElementById('checkoutModal');
    var checkoutBackdrop = document.getElementById('checkoutBackdrop');
    var checkoutClose = document.getElementById('checkoutClose');
    var checkoutForm = document.getElementById('checkoutForm');
    var websiteUrl = document.getElementById('websiteUrl');
    var customerEmail = document.getElementById('customerEmail');
    var urlError = document.getElementById('urlError');
    var emailError = document.getElementById('emailError');
    var checkoutSubmit = document.getElementById('checkoutSubmit');
    var submitText = checkoutSubmit && checkoutSubmit.querySelector('.checkout-modal__submit-text');
    var submitLoading = checkoutSubmit && checkoutSubmit.querySelector('.checkout-modal__submit-loading');

    function closeCheckout() {
      if (!checkoutModal) return;
      checkoutModal.classList.remove('open');
      checkoutModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    if (checkoutModal && checkoutForm) {
      checkoutBackdrop.addEventListener('click', closeCheckout);
      checkoutClose.addEventListener('click', closeCheckout);
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && checkoutModal.classList.contains('open')) closeCheckout();
      });

      checkoutForm.addEventListener('submit', function(e) {
        e.preventDefault();
        urlError.textContent = '';
        emailError.textContent = '';
        urlError.classList.remove('visible');
        emailError.classList.remove('visible');
        websiteUrl.classList.remove('error');
        customerEmail.classList.remove('error');

        if (!websiteUrl.value.trim()) {
          urlError.textContent = 'Please enter your website URL.';
          urlError.classList.add('visible');
          websiteUrl.classList.add('error');
          websiteUrl.focus();
          return;
        }
        if (customerEmail.value.trim() && !customerEmail.validity.valid) {
          emailError.textContent = 'Please enter a valid email address.';
          emailError.classList.add('visible');
          customerEmail.classList.add('error');
          customerEmail.focus();
          return;
        }

        checkoutSubmit.disabled = true;
        submitText.style.display = 'none';
        submitLoading.style.display = 'inline-flex';
        fetch('/api/checkout/create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ website_url: websiteUrl.value.trim(), email: customerEmail.value.trim() })
        })
          .then(function(response) {
            return response.json().then(function(data) {
              if (!response.ok) throw new Error(data.error || 'Unable to start checkout.');
              return data;
            });
          })
          .then(function(data) {
            window.location.href = data.checkout_url;
          })
          .catch(function(error) {
            urlError.textContent = error.message || 'Unable to start checkout. Please try again.';
            urlError.classList.add('visible');
            checkoutSubmit.disabled = false;
            submitText.style.display = '';
            submitLoading.style.display = 'none';
          });
      });
    }

    // Overflow nav
    var OVERFLOW_SVG = '<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';
    function rebuildOverflow() {
      var links = document.querySelector('.nav__links');
      var wrapper = document.querySelector('.nav__overflow');
      if (!links || !wrapper) return;
      var savedLinks = links.getAttribute('data-overflow-links');
      if (savedLinks) links.innerHTML = savedLinks;
      links.removeAttribute('data-overflow-links');
      var btn = wrapper.querySelector('.nav__overflow-btn');
      var drop = wrapper.querySelector('.nav__overflow-dropdown');
      if (!btn || !drop) return;
      if (window.innerWidth < 768) { btn.style.display = 'none'; drop.innerHTML = ''; return; }
      var cta = document.querySelector('.nav__cta');
      var lang = document.querySelector('.nav__inner > .lang-switch');
      var available = links.parentElement.clientWidth - (cta ? cta.offsetWidth + 20 : 0) - (lang ? lang.offsetWidth + 16 : 0);
      var total = 0, cut = links.children.length;
      for (var i = 0; i < links.children.length; i++) {
        total += links.children[i].offsetWidth + 32;
        if (total > available) { cut = i; break; }
      }
      if (cut >= links.children.length) { btn.style.display = 'none'; drop.innerHTML = ''; return; }
      links.setAttribute('data-overflow-links', links.innerHTML);
      var hidden = [];
      while (links.children.length > cut) hidden.push(links.removeChild(links.lastChild));
      btn.innerHTML = '<?= t("index.nav.more", "More") ?> ' + OVERFLOW_SVG;
      btn.style.display = 'flex';
      drop.innerHTML = '';
      hidden.forEach(function(el) { drop.appendChild(el); });
    }
    rebuildOverflow();
    var resizeTimer;
    window.addEventListener('resize', function() { clearTimeout(resizeTimer); resizeTimer = setTimeout(rebuildOverflow, 100); });

    // Dropdowns
    document.addEventListener('click', function(e) {
      var sw = e.target.closest('.lang-switch');
      if (sw) { sw.classList.toggle('open'); }
      else { document.querySelectorAll('.lang-switch.open').forEach(function(el) { el.classList.remove('open'); }); }
      var ovf = e.target.closest('.nav__overflow');
      if (ovf) { ovf.classList.toggle('open'); }
      else { document.querySelectorAll('.nav__overflow.open').forEach(function(el) { el.classList.remove('open'); }); }
    });

    // FAQ accordion
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.faq__question');
      if (!btn) return;
      var item = btn.closest('.faq__item');
      if (!item) return;
      var isOpen = item.classList.contains('is-open');
      // Close all other items
      document.querySelectorAll('.faq__item.is-open').forEach(function(el) {
        if (el !== item) {
          el.classList.remove('is-open');
          el.querySelector('.faq__question').setAttribute('aria-expanded', 'false');
        }
      });
      // Toggle current item
      if (isOpen) {
        item.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
      } else {
        item.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  </script>

  <?= $pageScripts ?? '' ?>
</body>
</html>
