<?php
/**
 * Blog index — hero + card grid are generated from pages/registry.php.
 */
$blogArticles = $siteRegistry['articles'];
usort($blogArticles, function ($a, $b) {
    $fa = !empty($a['featured']);
    $fb = !empty($b['featured']);
    if ($fa !== $fb) {
        return $fa ? -1 : 1;
    }
    return strcmp($b['date'], $a['date']);
});
?>
<main class="article-page">
    <div class="container">

      <div class="features-page__hero">
        <h1 class="features-page__title"><?= t('blog.hero.title') ?></h1>
        <p class="features-page__subtitle"><?= t('blog.hero.subtitle') ?></p>
      </div>

      <div class="blog-grid">
        <?php foreach ($blogArticles as $article): ?>
        <?php
            $i18n = $article['i18n'][$currentLang] ?? $article['i18n']['en'];
            $featured = !empty($article['featured']);
        ?>
        <a href="<?= navUrl($currentLang, $article['slug']) ?>" class="blog-card<?= $featured ? ' blog-card--featured' : '' ?>">
          <span class="blog-card__tag"><?= $i18n['tag'] ?></span>
          <h2 class="blog-card__title"><?= $i18n['card_title'] ?></h2>
          <p class="blog-card__excerpt"><?= $i18n['excerpt'] ?></p>
          <span class="blog-card__cta"><?= $i18n['cta'] ?></span>
        </a>
        <?php endforeach; ?>

      </div>

    </div>
  </main>

  <div id="pv-footer"></div>