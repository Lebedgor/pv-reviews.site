<?php
/**
 * PVR Media Reviews — PHP Router
 * Handles language detection, URL routing, and translation loading.
 *
 * Pages and blog articles are described in pages/registry.php; article
 * bodies live in lang/{lang}_articles/ with a fallback to lang/en_articles/.
 */

declare(strict_types=1);

$validLangs = ['en', 'ru', 'es', 'fr'];
$defaultLang = 'en';

// Language from nginx rewrite (_lang query param)
$lang = isset($_GET['_lang']) && in_array($_GET['_lang'], $validLangs, true)
    ? (string) $_GET['_lang']
    : $defaultLang;

// Page from _uri param (set by nginx rewrite) or REQUEST_URI
$rawUri = isset($_GET['_uri'])
    ? trim((string) $_GET['_uri'], '/')
    : trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');

// Remove leading language prefix if present
$pathParts = explode('/', $rawUri);
$hadExplicitLang = isset($pathParts[0]) && in_array($pathParts[0], $validLangs, true);
if ($hadExplicitLang) {
    array_shift($pathParts);
}

$page = implode('/', $pathParts);
if ($page === '' || $page === '/') {
    $page = 'index';
}

// Strip .html extension
$page = (string) preg_replace('/\.html$/', '', $page);

// Sanitize
$page = (string) preg_replace('/[^a-zA-Z0-9\-_]/', '', $page);

// English is served without the /en/ prefix: 301 /en/* -> clean URL.
// Preserve the query string — deep links like /en/renew?license=KEY.
// Other languages keep their prefix (/ru/ IS canonical for Russian).
if ($lang === 'en' && $hadExplicitLang) {
    $queryString = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    if ($queryString !== '') {
        $queryString = '?' . $queryString;
    }
    header('Location: ' . getPageUrl('en', $page) . $queryString, true, 301);
    exit;
}

// Load translations
$langFile = __DIR__ . '/lang/' . $lang . '.json';
$translations = [];
if (file_exists($langFile)) {
    $translations = json_decode((string) file_get_contents($langFile), true) ?: [];
}
if ($lang !== $defaultLang && empty($translations)) {
    $fallback = __DIR__ . '/lang/' . $defaultLang . '.json';
    $translations = json_decode((string) file_get_contents($fallback), true) ?: [];
}

// The English articles have a dedicated editorial source so long screenshot-heavy
// bodies remain readable and can be revised without editing one-line JSON values.
// Blog article bodies are resolved per language from lang/{lang}_articles/ and
// fall back to the English source when no translation exists. The main page
// article works the same way and is shown to all languages (it is the marketing
// core of the landing).
$pageKey = str_replace('-', '_', $page);
$siteRegistry = require __DIR__ . '/pages/registry.php';

// Localize internal links inside article bodies for non-English languages.
// Body files keep language-neutral hrefs (/blog-…, /#pricing, /); the router
// rewrites them to /{lang}/… at delivery time so cross-linking always follows
// the language the visitor is reading.
function localizeBodyLinks(string $body, string $lang): string {
    if ($lang === 'en') {
        return $body;
    }
    return str_replace(
        ['href="/#', 'href="/blog-', 'href="/blog"', 'href="/features', 'href="/settings', 'href="/renew', 'href="/"'],
        ['href="/' . $lang . '/#', 'href="/' . $lang . '/blog-', 'href="/' . $lang . '/blog"', 'href="/' . $lang . '/features', 'href="/' . $lang . '/settings', 'href="/' . $lang . '/renew', 'href="/' . $lang . '/"'],
        $body
    );
}

if ($page === 'index') {
    $langBody = __DIR__ . '/lang/' . $lang . '_articles/index.html';
    $indexBody = is_file($langBody) ? $langBody : __DIR__ . '/lang/en_articles/index.html';
    if (is_file($indexBody)) {
        $translations['index']['body'] = localizeBodyLinks((string) file_get_contents($indexBody), $lang);
    }
} elseif (isset($siteRegistry['articles'][$pageKey])) {
    $langBody = __DIR__ . '/lang/' . $lang . '_articles/' . $pageKey . '.html';
    $enBody = __DIR__ . '/lang/en_articles/' . $pageKey . '.html';
    $translations[$pageKey]['body'] = localizeBodyLinks((string) file_get_contents(is_file($langBody) ? $langBody : $enBody), $lang);
}

// Translation helper — dot-notation lookup
function t(string $key, $default = '') {
    global $translations;
    $keys = explode('.', $key);
    $value = $translations;
    foreach ($keys as $k) {
        if (is_array($value) && array_key_exists($k, $value)) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    return $value;
}

// Clean public URL for a page. English is the base language: no /en/ prefix —
// language-less URLs (/, /features, /blog, …) ARE the English version.
function getPageUrl(string $lang, string $page): string {
    $prefix = $lang === 'en' ? '' : '/' . $lang;
    if ($page === 'index') {
        return 'https://pv-reviews.site' . ($lang === 'en' ? '/' : $prefix . '/');
    }
    return 'https://pv-reviews.site' . $prefix . '/' . $page;
}

// Nav link helper: anchors always target the home page (article/pricing/faq
// live on the index page), pages get the language prefix for non-English.
function navUrl(string $lang, string $segment): string {
    $prefix = $lang === 'en' ? '' : '/' . $lang;
    if ($segment === '') {
        return $lang === 'en' ? '/' : $prefix . '/';
    }
    return $segment[0] === '#' ? ($prefix === '' ? '/' : $prefix) . $segment : $prefix . '/' . $segment;
}

// Hreflang alternate links
function getAlternateLinks(string $currentPage): string {
    global $validLangs;
    $html = '';
    foreach ($validLangs as $l) {
        $html .= '<link rel="alternate" hreflang="' . $l . '" href="' . getPageUrl($l, $currentPage) . '">' . "\n";
    }
    $html .= '<link rel="alternate" hreflang="x-default" href="' . getPageUrl('en', $currentPage) . '">';
    return $html;
}

// JSON-LD for a blog article, generated from the registry metadata.
function buildArticleJsonLd(array $siteRegistry, string $lang, string $pageKey): array {
    $article = $siteRegistry['articles'][$pageKey];
    $i18n = $article['i18n'][$lang] ?? $article['i18n']['en'];
    $prefix = $lang === 'en' ? '' : '/' . $lang;
    $home = 'https://pv-reviews.site' . ($lang === 'en' ? '/' : $prefix . '/');
    $pageUrl = 'https://pv-reviews.site' . $prefix . '/' . $article['slug'];
    return [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $i18n['title'],
            'description' => $i18n['description'],
            'author' => ['@type' => 'Organization', 'name' => 'PVR Media Reviews'],
            'publisher' => ['@type' => 'Organization', 'name' => 'PVR Media Reviews', 'url' => 'https://pv-reviews.site/'],
            'datePublished' => $article['date'],
            'dateModified' => $article['date'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $pageUrl],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => t('breadcrumbs.home', 'Home'), 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => t('breadcrumbs.blog', 'Blog'), 'item' => $home . 'blog'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $i18n['title']],
            ],
        ],
    ];
}

// Breadcrumb trail for every page except the home page (and 404).
function getBreadcrumbs(array $siteRegistry, string $lang, string $pageKey): array {
    if ($pageKey === 'index' || $pageKey === '404') {
        return [];
    }
    $home = ['label' => t('breadcrumbs.home', 'Home'), 'url' => navUrl($lang, '')];
    if ($pageKey === 'blog') {
        return [$home, ['label' => t('breadcrumbs.blog', 'Blog'), 'url' => navUrl($lang, 'blog')]];
    }
    if (isset($siteRegistry['articles'][$pageKey])) {
        $i18n = $siteRegistry['articles'][$pageKey]['i18n'][$lang]
            ?? $siteRegistry['articles'][$pageKey]['i18n']['en'];
        return [
            $home,
            ['label' => t('breadcrumbs.blog', 'Blog'), 'url' => navUrl($lang, 'blog')],
            ['label' => $i18n['title'], 'url' => ''],
        ];
    }
    $label = t('breadcrumbs.' . $pageKey);
    if ($label === '') {
        return [];
    }
    return [$home, ['label' => $label, 'url' => '']];
}

$currentPage = $page;
$currentLang = $lang;
$siteUrl = 'https://pv-reviews.site';

// Page meta: blog articles come from the registry, other pages from translations.
$meta = ['title' => '', 'description' => '', 'og_image' => ''];
if (isset($siteRegistry['articles'][$pageKey])) {
    $i18n = $siteRegistry['articles'][$pageKey]['i18n'][$lang]
        ?? $siteRegistry['articles'][$pageKey]['i18n']['en'];
    $meta['title'] = $i18n['title'] . ' — PVR Media Reviews';
    $meta['description'] = $i18n['description'];
    $meta['og_image'] = $siteRegistry['articles'][$pageKey]['og_image'];
} else {
    $meta['title'] = (string) t($pageKey . '.meta.title');
    $meta['description'] = (string) t($pageKey . '.meta.description');
    $meta['og_image'] = (string) t($pageKey . '.meta.og_image');
}

// JSON-LD structured data: blog articles are generated from the registry,
// other pages keep their per-page schema from the translation files.
$jsonLdRaw = isset($siteRegistry['articles'][$pageKey])
    ? buildArticleJsonLd($siteRegistry, $lang, $pageKey)
    : t($pageKey . '.jsonld');
$metaJsonLd = '';
if (is_array($jsonLdRaw) && !empty($jsonLdRaw)) {
    // Canonicalize Article mainEntityOfPage to the per-language URL.
    foreach ($jsonLdRaw as &$schemaItem) {
        if (($schemaItem['@type'] ?? '') === 'Article'
            && isset($schemaItem['mainEntityOfPage']['@id'])
            && strpos((string) $schemaItem['mainEntityOfPage']['@id'], '/en/') !== false
            && $lang !== 'en') {
            $schemaItem['mainEntityOfPage']['@id'] = str_replace('/en/', '/' . $lang . '/', (string) $schemaItem['mainEntityOfPage']['@id']);
        }
    }
    unset($schemaItem);
    $metaJsonLd = '<script type="application/ld+json">' . json_encode($jsonLdRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

// Resolve template: index and blog have their own, physical files come next,
// everything else uses the generic page.php fed from the translations.
if ($page === 'index') {
    $templateFile = __DIR__ . '/pages/index.php';
} elseif ($page === 'blog') {
    $templateFile = __DIR__ . '/pages/blog.php';
} elseif (file_exists(__DIR__ . '/pages/' . $page . '.php')) {
    $templateFile = __DIR__ . '/pages/' . $page . '.php';
} else {
    $templateFile = __DIR__ . '/pages/page.php';
}

if (!file_exists($templateFile)) {
    http_response_code(404);
    $page = '404';
    $currentPage = '404';
    $pageKey = '404';
    $templateFile = __DIR__ . '/pages/404.php';
}

// Soft-404: unknown page with no content in translations is a real 404.
if ($templateFile === __DIR__ . '/pages/page.php' && $page !== 'index' && t($pageKey . '.body') === '') {
    http_response_code(404);
    $page = '404';
    $currentPage = '404';
    $pageKey = '404';
    $templateFile = __DIR__ . '/pages/404.php';
}

$breadcrumbs = getBreadcrumbs($siteRegistry, $currentLang, $pageKey);

require __DIR__ . '/pages/layout.php';
