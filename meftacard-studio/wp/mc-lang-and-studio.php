<?php
/**
 * MEFTACARD — language attributes, hreflang and Studio landing pages.
 * Paste into Code Snippets (scope: "Run snippet everywhere") or a must-use plugin.
 *
 * 1. Correct <html lang/dir> per post language (fixes lang="fa-IR" on English/Arabic posts).
 *    Language = post meta `mc_lang` if set, otherwise by category:
 *    Arabic categories 31, 42 → ar · Persian category 37 → fa · other posts → en.
 * 2. hreflang pairs for posts that have `mc_alt` (URL of the translation).
 * 3. Pages with `mc_raw` = 1 output their post content as a complete HTML document
 *    (used for /studio/ and /ar/studio/); pages with `mc_redirect` 301 to that URL.
 */

const MC_AR_CATS = [31, 42];
const MC_FA_CATS = [37];

add_action('init', function () {
    $auth = function () { return current_user_can('edit_posts'); };
    foreach (['post', 'page'] as $type) {
        register_post_meta($type, 'mc_lang', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
        register_post_meta($type, 'mc_alt', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
    }
    register_post_meta('page', 'mc_raw', ['type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
    register_post_meta('page', 'mc_redirect', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
});

function mc_post_lang($id) {
    $lang = get_post_meta($id, 'mc_lang', true);
    if ($lang) return $lang;
    if (get_post_type($id) === 'post') {
        if (has_category(MC_AR_CATS, $id)) return 'ar';
        if (has_category(MC_FA_CATS, $id)) return 'fa';
        return 'en';
    }
    return '';
}

add_filter('language_attributes', function ($output) {
    if (is_admin() || !is_singular()) return $output;
    $lang = mc_post_lang(get_queried_object_id());
    if (!$lang) return $output;
    $dir  = in_array($lang, ['ar', 'fa'], true) ? 'rtl' : 'ltr';
    $code = $lang === 'fa' ? 'fa-IR' : $lang;
    $output = preg_replace('/\s*(dir|lang)="[^"]*"/', '', $output);
    return trim('dir="' . $dir . '" lang="' . esc_attr($code) . '" ' . $output);
}, 99);

add_action('wp_head', function () {
    if (!is_singular()) return;
    $id   = get_queried_object_id();
    $lang = mc_post_lang($id);
    $alt  = get_post_meta($id, 'mc_alt', true);
    if (!$lang || !$alt) return;
    $self     = get_permalink($id);
    $alt_lang = $lang === 'ar' ? 'en' : 'ar';
    $default  = $lang === 'en' ? $self : $alt;
    printf("<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">\n", esc_attr($lang), esc_url($self));
    printf("<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">\n", esc_attr($alt_lang), esc_url($alt));
    printf("<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\">\n", esc_url($default));
}, 2);

add_action('template_redirect', function () {
    if (!is_page()) return;
    $id = get_queried_object_id();
    $redirect = get_post_meta($id, 'mc_redirect', true);
    if ($redirect) { wp_redirect(esc_url_raw($redirect), 301); exit; }
    if (!get_post_meta($id, 'mc_raw', true)) return;
    if (get_post_status($id) !== 'publish' && !current_user_can('edit_post', $id)) return;
    header('Content-Type: text/html; charset=utf-8');
    echo get_post_field('post_content', $id, 'raw'); // full HTML document authored by an administrator
    exit;
}, 0);

/* ---------- Studio sitemap (v1.1) ----------
 * /studio-sitemap.xml lists every published Studio page (mc_raw) and the article
 * categories 41 (EN) and 42 (AR), with hreflang alternates. Served without rewrite
 * rules, added to the Rank Math sitemap index and to robots.txt. Redirect pages
 * (mc_redirect) are removed from Rank Math's own sitemaps.
 */
const MC_ARTICLE_CATS = [41, 42];

function mc_studio_sitemap_xml() {
    $pages = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 500,
        'meta_key' => 'mc_raw', 'meta_value' => '1']);
    $posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 500,
        'category__in' => MC_ARTICLE_CATS]);
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    $home = home_url('/');
    foreach ($pages as $p) {
        $url = get_permalink($p);
        $path = substr($url, strlen($home) - 1);                 // e.g. /ar/studio/iraq/
        $en = (strpos($path, '/ar/') === 0) ? home_url(substr($path, 3)) : $url;
        $ar = (strpos($path, '/ar/') === 0) ? $url : home_url('/ar' . $path);
        $out .= "<url><loc>" . esc_url($url) . "</loc><lastmod>" . get_post_modified_time('c', true, $p) . "</lastmod>"
              . '<xhtml:link rel="alternate" hreflang="en" href="' . esc_url($en) . '"/>'
              . '<xhtml:link rel="alternate" hreflang="ar" href="' . esc_url($ar) . '"/>'
              . '<xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url($en) . '"/>'
              . "</url>\n";
    }
    foreach ($posts as $p) {
        $out .= "<url><loc>" . esc_url(get_permalink($p)) . "</loc><lastmod>" . get_post_modified_time('c', true, $p) . "</lastmod>";
        $alt = get_post_meta($p->ID, 'mc_alt', true);
        if ($alt) {
            $lang = mc_post_lang($p->ID);
            $out .= '<xhtml:link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url(get_permalink($p)) . '"/>'
                  . '<xhtml:link rel="alternate" hreflang="' . ($lang === 'ar' ? 'en' : 'ar') . '" href="' . esc_url($alt) . '"/>';
        }
        $out .= "</url>\n";
    }
    return $out . '</urlset>';
}

add_action('template_redirect', function () {
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path !== '/studio-sitemap.xml' && !isset($_GET['mc_sitemap'])) return;
    status_header(200);
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
    echo mc_studio_sitemap_xml();
    exit;
}, -1);

add_filter('rank_math/sitemap/index', function ($xml) {
    return $xml . '<sitemap><loc>' . esc_url(home_url('/studio-sitemap.xml')) . '</loc><lastmod>' . esc_html(gmdate('c')) . '</lastmod></sitemap>' . "\n";
});

add_filter('robots_txt', function ($output) {
    return rtrim($output) . "\nSitemap: " . home_url('/studio-sitemap.xml') . "\n";
}, 99);

add_filter('rank_math/sitemap/entry', function ($url, $type, $object) {
    if ($type === 'post' && is_object($object) && !empty($object->ID) && get_post_meta($object->ID, 'mc_redirect', true)) return false;
    return $url;
}, 10, 3);
