<?php
/**
 * 404 page content — included by pages/layout.php.
 * HTTP status code 404 is set by router.php before including layout.
 */
$notFoundTexts = [
    'en' => ['Page not found', "The page you are looking for doesn't exist or has been moved.", 'Back to Home'],
    'ru' => ['Страница не найдена', 'Страница, которую вы ищете, не существует или была перемещена.', 'На главную'],
    'es' => ['Página no encontrada', 'La página que buscas no existe o ha sido movida.', 'Volver al inicio'],
    'fr' => ['Page introuvable', "La page que vous recherchez n'existe pas ou a été déplacée.", "Retour à l'accueil"],
];
$nf = $notFoundTexts[$currentLang] ?? $notFoundTexts['en'];
?>
<section style="min-height:70vh; display:flex; align-items:center; justify-content:center; text-align:center; padding:60px 20px;">
  <div>
    <div style="font-family:var(--font-mono); font-size:13px; font-weight:700; color:var(--accent); background:var(--accent-subtle); border:1px solid var(--accent-border); border-radius:9999px; display:inline-block; padding:4px 14px; margin-bottom:20px; letter-spacing:0.06em;">ERROR 404</div>
    <h1 style="font-size:clamp(34px,4.5vw,52px); font-weight:800; letter-spacing:-0.03em; color:var(--text-primary); margin-bottom:14px;"><?= htmlspecialchars($nf[0]) ?></h1>
    <p style="font-size:16.5px; color:var(--text-secondary); max-width:440px; margin:0 auto 32px; line-height:1.65;"><?= htmlspecialchars($nf[1]) ?></p>
    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
      <a href="<?= getPageUrl($currentLang, 'index') ?>" class="btn btn--primary"><?= htmlspecialchars($nf[2]) ?></a>
      <a href="<?= navUrl($currentLang, 'features') ?>" class="btn btn--secondary"><?= htmlspecialchars(t('index.nav.features', 'Features')) ?></a>
      <a href="<?= navUrl($currentLang, 'blog') ?>" class="btn btn--secondary"><?= htmlspecialchars(t('index.nav.blog', 'Blog')) ?></a>
    </div>
  </div>
</section>
