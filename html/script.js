document.addEventListener('DOMContentLoaded', () => {
  AOS.init({
    once: true,
    easing: 'ease-out-cubic',
  });

  function initNav() {
    const nav = document.getElementById('nav');
    const toggle = document.getElementById('mobileToggle');
    const menu = document.getElementById('mobileMenu');
    if (!nav || !toggle || !menu) return;

    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 10);
    });

    toggle.addEventListener('click', () => {
      toggle.classList.toggle('active');
      menu.classList.toggle('open');
    });

    menu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        toggle.classList.remove('active');
        menu.classList.remove('open');
      });
    });
  }

  initNav();
  document.addEventListener('pv-components-loaded', initNav);

  /* --- Lightbox --- */
  const lightbox = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightboxImg');
  const lightboxCaption = document.getElementById('lightboxCaption');
  const lightboxClose = document.getElementById('lightboxClose');
  const lightboxPrev = document.getElementById('lightboxPrev');
  const lightboxNext = document.getElementById('lightboxNext');
  const lightboxCounter = document.getElementById('lightboxCounter');
  const demoItems = Array.from(document.querySelectorAll('.demo__item'));
  let currentIndex = 0;

  function openLightbox(index) {
    currentIndex = index;
    const item = demoItems[currentIndex];
    const img = item.querySelector('img');
    if (!img) return;
    lightboxImg.style.display = 'none';
    lightboxImg.onload = () => { lightboxImg.style.display = ''; };
    lightboxImg.src = img.getAttribute('src');
    lightboxImg.alt = img.alt;
    lightboxCaption.textContent = item.dataset.caption || '';
    lightboxCounter.textContent = (currentIndex + 1) + ' / ' + demoItems.length;
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function navigateLightbox(direction) {
    currentIndex = (currentIndex + direction + demoItems.length) % demoItems.length;
    openLightbox(currentIndex);
  }

  demoItems.forEach((item, index) => {
    item.addEventListener('click', () => openLightbox(index));
  });

  function closeLightbox() {
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  lightboxClose.addEventListener('click', closeLightbox);
  lightboxPrev.addEventListener('click', (e) => { e.stopPropagation(); navigateLightbox(-1); });
  lightboxNext.addEventListener('click', (e) => { e.stopPropagation(); navigateLightbox(1); });
  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });
  document.addEventListener('keydown', (e) => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') navigateLightbox(-1);
    if (e.key === 'ArrowRight') navigateLightbox(1);
  });

  /* --- FAQ Accordion --- */
  document.querySelectorAll('.faq__question').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq__item');
      const wasOpen = item.classList.contains('is-open');
      document.querySelectorAll('.faq__item.is-open').forEach(open => open.classList.remove('is-open'));
      if (!wasOpen) {
        item.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
      } else {
        btn.setAttribute('aria-expanded', 'false');
      }
    });
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        btn.click();
      }
    });
  });

  /* --- Checkout Modal --- */
  const checkoutModal = document.getElementById('checkoutModal');
  const checkoutBackdrop = document.getElementById('checkoutBackdrop');
  const checkoutClose = document.getElementById('checkoutClose');
  const checkoutForm = document.getElementById('checkoutForm');
  const websiteUrlInput = document.getElementById('websiteUrl');
  const urlError = document.getElementById('urlError');
  const customerEmailInput = document.getElementById('customerEmail');
  const emailError = document.getElementById('emailError');
  const checkoutSubmit = document.getElementById('checkoutSubmit');
  const submitText = checkoutSubmit.querySelector('.checkout-modal__submit-text');
  const submitLoading = checkoutSubmit.querySelector('.checkout-modal__submit-loading');

  function openCheckoutModal() {
    checkoutModal.classList.add('open');
    checkoutModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    websiteUrlInput.focus();
  }

  function closeCheckoutModal() {
    checkoutModal.classList.remove('open');
    checkoutModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    resetCheckoutForm();
  }

  function resetCheckoutForm() {
    checkoutForm.reset();
    urlError.textContent = '';
    urlError.classList.remove('visible');
    websiteUrlInput.classList.remove('error');
    checkoutSubmit.disabled = false;
    submitText.style.display = '';
    submitLoading.style.display = 'none';
  }

  function setCheckoutLoading(loading) {
    checkoutSubmit.disabled = loading;
    submitText.style.display = loading ? 'none' : '';
    submitLoading.style.display = loading ? '' : 'none';
  }

  function showUrlError(message) {
    urlError.textContent = message;
    urlError.classList.add('visible');
    websiteUrlInput.classList.add('error');
  }

  // Wire up all "Get PVR Pro" buttons
  document.querySelectorAll('a[href="#pricing"], a[href="#"]').forEach(link => {
    if (link.textContent.includes('Get PVR Pro')) {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        openCheckoutModal();
      });
    }
  });

  checkoutBackdrop.addEventListener('click', closeCheckoutModal);
  checkoutClose.addEventListener('click', closeCheckoutModal);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && checkoutModal.classList.contains('open')) {
      closeCheckoutModal();
    }
  });

  checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    urlError.textContent = '';
    urlError.classList.remove('visible');
    websiteUrlInput.classList.remove('error');
    emailError.textContent = '';
    emailError.classList.remove('visible');
    customerEmailInput.classList.remove('error');

    const url = websiteUrlInput.value.trim();
    const email = customerEmailInput.value.trim();

    if (!url) {
      showUrlError('Please enter a website URL.');
      websiteUrlInput.focus();
      return;
    }

    let testUrl = url;
    if (!/^https?:\/\//i.test(testUrl)) {
      testUrl = 'https://' + testUrl;
    }

    try {
      new URL(testUrl);
    } catch {
      showUrlError('Please enter a valid URL (e.g., https://example.com).');
      websiteUrlInput.focus();
      return;
    }

    if (/^(javascript|data|vbscript):/i.test(testUrl)) {
      showUrlError('Invalid URL format.');
      websiteUrlInput.focus();
      return;
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      emailError.textContent = 'Please enter a valid email address.';
      emailError.classList.add('visible');
      customerEmailInput.classList.add('error');
      customerEmailInput.focus();
      return;
    }

    setCheckoutLoading(true);

    try {
      const response = await fetch('/api/checkout/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ website_url: url, email: email }),
      });

      const data = await response.json();

      if (!response.ok) {
        showUrlError(data.error || 'Something went wrong. Please try again.');
        setCheckoutLoading(false);
        return;
      }

      if (data.checkout_url) {
        window.location.href = data.checkout_url;
      } else {
        showUrlError('Failed to create checkout. Please try again.');
        setCheckoutLoading(false);
      }
    } catch (err) {
      showUrlError('Network error. Please check your connection and try again.');
      setCheckoutLoading(false);
    }
  });
});
