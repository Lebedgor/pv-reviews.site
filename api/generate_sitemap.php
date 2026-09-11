<?php
/**
 * Regenerates sitemap.xml from pages/registry.php.
 * Run after adding or changing articles/pages:
 *   php api/generate_sitemap.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
$registry = require $root . '/pages/registry.php';
$langs = ['en', 'ru', 'es', 'fr'];
$siteUrl = 'https://pv-reviews.site';
$today = date('Y-m-d');

$urls = [];

foreach ($registry['static_pages'] as $static) {
    $slug = $static['slug'];
    foreach ($langs as $lang) {
        $prefix = $lang === 'en' ? '' : '/' . $lang;
        $loc = $slug === 'index'
            ? $siteUrl . ($lang === 'en' ? '/' : $prefix . '/')
            : $siteUrl . $prefix . '/' . $slug;
        $urls[] = [
            'loc' => $loc,
            'lastmod' => $today,
            'changefreq' => $static['changefreq'],
            'priority' => $static['priority'],
        ];
    }
}

foreach ($registry['articles'] as $article) {
    foreach ($langs as $lang) {
        $prefix = $lang === 'en' ? '' : '/' . $lang;
        $urls[] = [
            'loc' => $siteUrl . $prefix . '/' . $article['slug'],
            'lastmod' => $article['date'],
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];
    }
}

$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>{$u['loc']}</loc>\n";
    $xml .= "    <lastmod>{$u['lastmod']}</lastmod>\n";
    $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
    $xml .= "    <priority>{$u['priority']}</priority>\n";
    $xml .= "  </url>\n";
}
$xml .= "</urlset>\n";

file_put_contents($root . '/sitemap.xml', $xml);
echo "sitemap.xml written: " . count($urls) . " URLs\n";
