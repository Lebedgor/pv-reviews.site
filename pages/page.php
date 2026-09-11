<?php
/**
 * Generic page template — outputs the body HTML stored in JSON translations.
 * Works for: features, settings, thank-you, blog, and all blog articles.
 */
$body = t($pageKey . '.body', '');
if ($body) {
    echo $body;
} else {
    echo '<section class="page-hero"><div class="container"><h1>Page not found</h1></div></section>';
}
