<!-- ===== FUNCTIONALITY ARTICLE ===== -->
<?php $articleBody = trim((string) t('index.body', '')); ?>
<?php if ($articleBody !== ''): ?>
<section id="article" class="pvr-article-section">
  <div class="container">
    <?= $articleBody ?>
  </div>
</section>
<?php endif; ?>

<!-- ===== PRICING ===== -->
<section id="pricing" class="pricing">
  <div class="container">
    <div class="pricing__header" data-aos="fade-up">
      <span class="ps__label"><?= t('index.pricing.label') ?></span>
      <h2 class="ps__title"><?= t('index.pricing.title') ?></h2>
    </div>

    <div class="founding-offer" data-aos="fade-up" data-aos-delay="100">
      <div class="founding-offer__badge" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor"/></svg>
      </div>
      <div class="founding-offer__body">
        <span class="founding-offer__tag"><?= t('index.pricing.founding.tag') ?></span>
        <p class="founding-offer__text"><?= t('index.pricing.founding.text') ?></p>
      </div>
      <div class="founding-offer__count">
        <span class="founding-offer__count-num"><?= t('index.pricing.founding.count_num') ?></span>
        <span class="founding-offer__count-label"><?= t('index.pricing.founding.count_label') ?></span>
      </div>
    </div>

    <div class="pricing__grid">
      <!-- FREE -->
      <div class="pricing__card" data-aos="fade-right" data-aos-duration="700">
        <div class="pricing__card-head">
          <h3 class="pricing__plan">Free</h3>
          <p class="pricing__price">Free forever</p>
        </div>
        <ul class="pricing__features">
          <?php foreach (t('index.pricing.features_free', []) as $feat): ?>
          <li class="pricing__feature"><span class="pricing__check">&#10003;</span> <?= $feat ?></li>
          <?php endforeach; ?>
        </ul>
        <a href="https://wordpress.org/plugins/pvr-media-reviews-for-woocommerce/" target="_blank" class="btn btn--secondary pricing__btn"><?= t('index.hero.cta_free') ?></a>
      </div>
      <!-- PRO -->
      <div class="pricing__card pricing__card--pro" data-aos="fade-left" data-aos-duration="700">
        <div class="pricing__card-head">
          <span class="pricing__badge">Most Popular</span>
          <h3 class="pricing__plan">Pro</h3>
          <p class="pricing__price">$59 <span class="pricing__price-note">One-time purchase &middot; Single site license</span></p>
        </div>
        <div class="pricing__info">
          <div class="pricing__info-block">
            <span class="pricing__info-heading"><?= t('index.pricing.includes.heading') ?></span>
            <span class="pricing__info-item"><span class="pricing__info-check">&#10003;</span> <?= t('index.pricing.includes.item1') ?></span>
            <span class="pricing__info-item"><span class="pricing__info-check">&#10003;</span> <?= t('index.pricing.includes.item2') ?></span>
          </div>
          <div class="pricing__info-block">
            <span class="pricing__info-heading"><?= t('index.pricing.includes.founding_heading') ?></span>
            <span class="pricing__info-item"><?= t('index.pricing.includes.founding_item') ?></span>
          </div>
        </div>
        <ul class="pricing__features">
          <?php foreach (t('index.pricing.features_pro', []) as $feat): ?>
          <li class="pricing__feature"><span class="pricing__check">&#10003;</span> <?= $feat ?></li>
          <?php endforeach; ?>
        </ul>
        <a href="#" class="btn btn--primary pricing__btn" onclick="document.getElementById('checkoutModal').classList.add('open'); return false;"><?= t('index.hero.cta_pro') ?></a>
      </div>
    </div>
  </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="faq">
  <div class="container">
    <div class="ps__header" data-aos="fade-up">
      <h2 class="ps__title"><?= t('index.faq.title') ?></h2>
      <p class="ps__subtitle"><?= t('index.faq.subtitle') ?></p>
    </div>
    <div class="faq__list" data-aos="fade-up" data-aos-delay="100">
      <?php foreach (t('index.faq.items', []) as $item): ?>
      <div class="faq__item">
        <button class="faq__question" aria-expanded="false">
          <span><?= $item['q'] ?></span>
          <svg class="faq__icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5 10h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path class="faq__icon-v" d="M10 5v10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </button>
        <div class="faq__answer">
          <div class="faq__answer-inner">
            <p><?= $item['a'] ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
