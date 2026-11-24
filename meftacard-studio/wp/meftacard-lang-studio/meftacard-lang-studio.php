<?php
/**
 * Plugin Name: MEFTACARD Lang & Studio
 * Description: Correct html lang/dir per post language, hreflang for translated posts, full-HTML Studio landing pages, and a Studio sitemap (/studio-sitemap.xml).
 * Version: 1.8.0
 * Author: MEFTACARD
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;
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
    $canon = redirect_canonical(null, false);   // v1.4: keep www/slash/case/https redirects
    if ($canon) { wp_safe_redirect($canon, 301); exit; }
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
    // v1.8: every raw Studio page lists the language versions that actually exist (en, ar, fa).
    $paths = [];
    foreach ($pages as $p) $paths[substr(get_permalink($p), strlen($home) - 1)] = true;
    foreach ($pages as $p) {
        $url  = get_permalink($p);
        $path = substr($url, strlen($home) - 1);                 // e.g. /ar/studio/iraq/ or /fa/studio/
        $base = preg_replace('~^/(ar|fa)(?=/)~', '', $path);     // /studio/iraq/
        $alts = [];
        foreach (['en' => $base, 'ar' => '/ar' . $base, 'fa' => '/fa' . $base] as $lg => $pp) {
            if (isset($paths[$pp])) $alts[$lg] = home_url($pp);
        }
        $out .= "<url><loc>" . esc_url($url) . "</loc><lastmod>" . get_post_modified_time('c', true, $p) . "</lastmod>";
        if (count($alts) > 1) {
            foreach ($alts as $lg => $u) $out .= '<xhtml:link rel="alternate" hreflang="' . $lg . '" href="' . esc_url($u) . '"/>';
            $out .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url($alts['en'] ?? $url) . '"/>';
        }
        $out .= "</url>\n";
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
    if ($type === 'post' && is_object($object) && !empty($object->ID)) {
        if (get_post_meta($object->ID, 'mc_redirect', true)) return false;
        if (function_exists('wc_get_page_id')) {                       // v1.4: cart/checkout/account are noindex
            foreach (['cart', 'checkout', 'myaccount'] as $p) if ((int) wc_get_page_id($p) === (int) $object->ID) return false;
        }
    }
    return $url;
}, 10, 3);

/* ---------- v1.2: real <h1> on single posts ----------
 * The theme prints the post title as <h2><a>…</a></h2>, so articles have no H1.
 * On single posts we turn that first heading into a proper <h1>.
 */
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_robots()) return;
    $cat = is_category() ? get_queried_object() : null;
    $singular = is_singular();
    ob_start(function ($html) use ($cat, $singular) {
        if (stripos($html, '<html') === false) return $html;             // only touch HTML documents
        // header logo: give the image a real alt text (v1.4)
        $html = preg_replace('#(<a class="brand"[^>]*>\s*<img[^>]*?)alt=""#', '$1alt="MEFTACARD"', $html, 1);
        if ($singular) {
            $html = preg_replace('#<article>\s*<h2>\s*<a href="[^"]*">(.*?)</a>\s*</h2>#s',
                '<article><h1 class="entry-title">$1</h1>', $html, 1);
        } elseif ($cat) {
            $desc = trim(strip_tags(term_description($cat->term_id)));
            $head = '<header class="mc-archive"><h1 class="entry-title">' . esc_html($cat->name) . '</h1>'
                  . ($desc ? '<p class="mc-archive-desc">' . esc_html($desc) . '</p>' : '') . '</header>';
            $html = preg_replace('#<main>#', '<main>' . $head, $html, 1);
        }
        return $html;
    });
}, 1);

add_action('wp_head', function () {
    if (!is_singular() && !is_category()) return;
    echo '<style>.mc-archive{margin:0 0 24px}.mc-archive-desc{opacity:.8;max-width:70ch}article>h1.entry-title,.mc-archive h1.entry-title{font-size:clamp(28px,4.2vw,44px);line-height:1.25;margin:0 0 18px;font-weight:700}</style>' . "\n";
}, 50);

/* ---------- v1.3: post to the Telegram channel ----------
 * Settings → MEFTACARD Telegram: the owner stores the bot token and channel here
 * (never in code or chat). Administrators can then post through
 * POST /wp-json/mc/v1/telegram  {text, photo?}   and check setup with
 * GET  /wp-json/mc/v1/telegram/status  (never returns the token).
 */
add_action('admin_menu', function () {
    add_options_page('MEFTACARD Telegram', 'MEFTACARD Telegram', 'manage_options', 'mc-telegram', function () {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['mc_tg_save']) && check_admin_referer('mc_tg_save')) {
            $token = trim(sanitize_text_field(wp_unslash($_POST['mc_tg_token'] ?? '')));
            if ($token !== '') update_option('mc_tg_token', $token, false);
            update_option('mc_tg_chat', trim(sanitize_text_field(wp_unslash($_POST['mc_tg_chat'] ?? '@meftacardchannel'))), false);
            update_option('mc_tg_auto', empty($_POST['mc_tg_auto']) ? '0' : '1', false);
            echo '<div class="updated"><p>Saved.</p></div>';
        }
        $has = get_option('mc_tg_token') ? 'saved (hidden)' : 'not set';
        $chat = esc_attr(get_option('mc_tg_chat', '@meftacardchannel'));
        echo '<div class="wrap"><h1>MEFTACARD Telegram</h1><form method="post">';
        wp_nonce_field('mc_tg_save');
        echo '<table class="form-table"><tr><th>Bot token</th><td><input type="password" name="mc_tg_token" class="regular-text" autocomplete="off" placeholder="' . esc_attr($has) . '"><p class="description">From @BotFather. Leave empty to keep the saved token.</p></td></tr>';
        echo '<tr><th>Channel</th><td><input name="mc_tg_chat" class="regular-text" value="' . $chat . '"><p class="description">e.g. @meftacardchannel. Add the bot to the channel as an administrator with permission to post.</p></td></tr>';
        echo '<tr><th>Auto-post</th><td><label><input type="checkbox" name="mc_tg_auto" value="1" ' . checked(get_option('mc_tg_auto', '1'), '1', false) . '> Post new articles to the channel when they are published</label><p class="description">Only posts in the Studio article categories, only once per article.</p></td></tr></table>';
        echo '<p><button class="button button-primary" name="mc_tg_save" value="1">Save</button></p></form></div>';
    });
});

function mc_tg_call($method, $args) {
    $token = get_option('mc_tg_token');
    if (!$token) return new WP_Error('mc_tg_no_token', 'Telegram bot token is not set (Settings → MEFTACARD Telegram).', ['status' => 400]);
    $r = wp_remote_post('https://api.telegram.org/bot' . $token . '/' . $method, ['timeout' => 25, 'body' => $args]);
    if (is_wp_error($r)) return new WP_Error('mc_tg_http', $r->get_error_message(), ['status' => 502]);
    $body = json_decode(wp_remote_retrieve_body($r), true);
    if (empty($body['ok'])) return new WP_Error('mc_tg_api', $body['description'] ?? 'Telegram error', ['status' => 502]);
    return $body['result'];
}

add_action('rest_api_init', function () {
    $admin = function () { return current_user_can('manage_options'); };
    register_rest_route('mc/v1', '/telegram/status', ['methods' => 'GET', 'permission_callback' => $admin, 'callback' => function () {
        $chat = get_option('mc_tg_chat', '@meftacardchannel');
        if (!get_option('mc_tg_token')) return ['token' => false, 'chat' => $chat];
        $me = mc_tg_call('getMe', []);
        if (is_wp_error($me)) return $me;
        $c = mc_tg_call('getChat', ['chat_id' => $chat]);
        $member = is_wp_error($c) ? $c : mc_tg_call('getChatMember', ['chat_id' => $chat, 'user_id' => $me['id']]);
        return ['token' => true, 'bot' => '@' . $me['username'], 'chat' => $chat,
                'chat_ok' => !is_wp_error($c), 'bot_status' => is_wp_error($member) ? $member->get_error_message() : ($member['status'] ?? ''),
                'can_post' => !is_wp_error($member) && (($member['status'] ?? '') === 'creator' || !empty($member['can_post_messages'])),
                'auto_post' => get_option('mc_tg_auto', '1') === '1'];
    }]);
    register_rest_route('mc/v1', '/telegram', ['methods' => 'POST', 'permission_callback' => $admin, 'callback' => function (WP_REST_Request $req) {
        $text = (string) $req->get_param('text');
        if (trim($text) === '') return new WP_Error('mc_tg_empty', 'text is required', ['status' => 400]);
        $chat = get_option('mc_tg_chat', '@meftacardchannel');
        $photo = (string) $req->get_param('photo');
        $res = $photo
            ? mc_tg_call('sendPhoto', ['chat_id' => $chat, 'photo' => esc_url_raw($photo), 'caption' => mb_substr($text, 0, 1024)])
            : mc_tg_call('sendMessage', ['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'false']);
        if (is_wp_error($res)) return $res;
        if ($req->get_param('pin')) mc_tg_call('pinChatMessage', ['chat_id' => $chat, 'message_id' => $res['message_id'], 'disable_notification' => 'true']);
        return ['ok' => true, 'message_id' => $res['message_id'], 'link' => 'https://t.me/' . ltrim($chat, '@') . '/' . $res['message_id']];
    }]);
});

/* ---------- v1.4: language on category archives and regular pages ---------- */
add_filter('language_attributes', function ($output) {
    if (is_admin()) return $output;
    $lang = '';
    if (is_category()) {
        $id = get_queried_object_id();
        $lang = in_array($id, MC_AR_CATS, true) ? 'ar' : (in_array($id, MC_FA_CATS, true) ? 'fa' : 'en');
    } elseif (is_page() && !get_post_meta(get_queried_object_id(), 'mc_lang', true)
              && !(function_exists('is_woocommerce') && (is_cart() || is_checkout() || is_account_page()))) {
        $lang = 'en';
    }
    if (!$lang) return $output;
    $dir  = in_array($lang, ['ar', 'fa'], true) ? 'rtl' : 'ltr';
    $code = $lang === 'fa' ? 'fa-IR' : $lang;
    $output = preg_replace('/\s*(dir|lang)="[^"]*"/', '', $output);
    return trim('dir="' . $dir . '" lang="' . esc_attr($code) . '" ' . $output);
}, 100);

/* ---------- v1.5: auto-post new Studio articles to the Telegram channel ----------
 * Fires once, when a post in MC_ARTICLE_CATS becomes published for the first time.
 * Runs after the response is sent (no delay for the editor), retries twice on error,
 * and never posts the same article twice (meta mc_tg_posted).
 */
add_action('init', function () {
    $auth = function () { return current_user_can('edit_posts'); };
    register_post_meta('post', 'mc_tg_posted', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
    register_post_meta('post', 'mc_tg_error', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $auth]);
});

function mc_tg_article_text($id) {
    $ar    = has_category(MC_AR_CATS, $id);
    $title = trim(html_entity_decode(wp_strip_all_tags(get_the_title($id)), ENT_QUOTES, 'UTF-8'));
    $p     = get_post($id);
    $ex    = $p->post_excerpt !== '' ? $p->post_excerpt : wp_trim_words(wp_strip_all_tags(strip_shortcodes($p->post_content)), 40, '…');
    $ex    = trim(html_entity_decode(wp_strip_all_tags($ex), ENT_QUOTES, 'UTF-8'));
    if (mb_strlen($ex) > 320) $ex = rtrim(mb_substr($ex, 0, 317)) . '…';
    $link  = get_permalink($id);
    return $ar
        ? "🔹 {$title}\n\n{$ex}\n\n📖 اقرأ المقال:\n{$link}\n\n🇮🇶 العراق · 🇦🇪 دبي · 🇸🇦 السعودية · 🇴🇲 عُمان\n💬 للاستشارة: @meftacard"
        : "🔹 {$title}\n\n{$ex}\n\n📖 Read the article:\n{$link}\n\n🇮🇶 Iraq · 🇦🇪 Dubai · 🇸🇦 Saudi Arabia · 🇴🇲 Oman\n💬 @meftacard";
}

function mc_tg_autopost($id) {
    $id = (int) $id;
    if (get_option('mc_tg_auto', '1') !== '1' || !get_option('mc_tg_token')) return;
    $p = get_post($id);
    if (!$p || $p->post_type !== 'post' || $p->post_status !== 'publish') return;
    if (!has_category(MC_ARTICLE_CATS, $id) || get_post_meta($id, 'mc_tg_posted', true)) return;
    if (get_transient('mc_tg_lock_' . $id)) return;                 // another request is already posting it
    set_transient('mc_tg_lock_' . $id, 1, 120);
    $res = mc_tg_call('sendMessage', ['chat_id' => get_option('mc_tg_chat', '@meftacardchannel'),
                                       'text' => mc_tg_article_text($id), 'disable_web_page_preview' => 'false']);
    if (is_wp_error($res)) {
        $n = (int) get_post_meta($id, 'mc_tg_tries', true) + 1;
        update_post_meta($id, 'mc_tg_tries', $n);
        update_post_meta($id, 'mc_tg_error', $res->get_error_message());
        delete_transient('mc_tg_lock_' . $id);
        if ($n < 3) wp_schedule_single_event(time() + 600 * $n, 'mc_tg_autopost_retry', [$id]);
        return;
    }
    update_post_meta($id, 'mc_tg_posted', (string) $res['message_id']);
    delete_post_meta($id, 'mc_tg_error');
    delete_post_meta($id, 'mc_tg_tries');
}
add_action('mc_tg_autopost_retry', 'mc_tg_autopost');

add_action('wp_after_insert_post', function ($post_id, $post, $update, $post_before) {
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') return;
    if ($post_before && $post_before->post_status === 'publish') return;   // edits of published posts never post
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    $GLOBALS['mc_tg_queue'][] = (int) $post_id;
}, 10, 4);

add_action('shutdown', function () {
    if (empty($GLOBALS['mc_tg_queue'])) return;
    $ids = array_unique($GLOBALS['mc_tg_queue']);
    $GLOBALS['mc_tg_queue'] = [];
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();   // editor gets its response first
    foreach ($ids as $id) mc_tg_autopost($id);
});

// Preview the text that would be posted, without sending anything.
add_action('rest_api_init', function () {
    register_rest_route('mc/v1', '/telegram/preview/(?P<id>\d+)', ['methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function (WP_REST_Request $r) {
            $id = (int) $r['id'];
            if (!get_post($id)) return new WP_Error('mc_tg_nopost', 'No such post', ['status' => 404]);
            return ['id' => $id, 'eligible' => has_category(MC_ARTICLE_CATS, $id), 'already_posted' => get_post_meta($id, 'mc_tg_posted', true),
                    'error' => get_post_meta($id, 'mc_tg_error', true), 'text' => mc_tg_article_text($id)];
        }]);
});

/* ---------- v1.6: brand logo + site icon on the flagship homepage ----------
 * The homepage is rendered by another plugin without wp_head(), so it shows a plain "M"
 * and no browser icon. We swap the "M" for the MEFTACARD key mark and add the site icon.
 */
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_robots()) return;
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if ($path !== '' && !is_front_page() && !is_home()) return;
    ob_start(function ($html) {
        if (stripos($html, 'brand-mark') === false) return $html;
        $svg = '<svg class="mc-key" viewBox="0 4 100 64" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M22 4a22 22 0 1 1 0 44a22 22 0 1 1 0-44zm0 9a13 13 0 1 0 0 26a13 13 0 1 0 0-26z"/><path fill-rule="evenodd" d="M42 21.5H100V68H91V45.5L79 61.5L67 45.5V68H58V30.5H42Z M67 30.5L79 46.5L91 30.5Z"/></svg>';
        $html = preg_replace('~<span class="brand-mark">\s*M\s*</span>~', '<span class="brand-mark mc-logo">' . $svg . '</span>', $html, 1);
        $head = '<style>.brand-mark.mc-logo{color:inherit!important;background:none!important;border:0!important;box-shadow:none!important;width:auto!important;height:auto!important;padding:0!important;display:inline-flex;align-items:center}.brand-mark.mc-logo .mc-key{width:34px;height:22px;fill:currentColor;display:block}</style>';
        if (stripos($html, 'rel="icon"') === false && function_exists('get_site_icon_url') && get_site_icon_url()) {
            $head .= '<link rel="icon" href="' . esc_url(get_site_icon_url(32)) . '" sizes="32x32" />'
                   . '<link rel="icon" href="' . esc_url(get_site_icon_url(192)) . '" sizes="192x192" />'
                   . '<link rel="apple-touch-icon" href="' . esc_url(get_site_icon_url(180)) . '" />';
        }
        // Homepage title was 78 characters (Google cuts at ~60) and there were no social preview tags.
        if (preg_match('~<title>([^<]*)</title>~i', $html, $tm) && mb_strlen(html_entity_decode($tm[1])) > 60) {
            $html = str_replace($tm[0], '<title>MEFTACARD — AI, Virtual Cards &amp; Non-VoIP SIMs (USDT)</title>', $html);
        }
        if (stripos($html, 'property="og:') === false) {
            $t = 'MEFTACARD — AI, Virtual Cards & Non-VoIP SIMs (USDT)';
            $d = preg_match('~<meta name="description" content="([^"]*)"~i', $html, $dm) ? html_entity_decode($dm[1]) : '';
            $img = function_exists('get_site_icon_url') ? get_site_icon_url(512) : '';
            $head .= '<meta property="og:type" content="website" /><meta property="og:site_name" content="MEFTACARD" />'
                   . '<meta property="og:url" content="' . esc_url(home_url('/')) . '" />'
                   . '<meta property="og:title" content="' . esc_attr($t) . '" />'
                   . '<meta property="og:description" content="' . esc_attr($d) . '" />'
                   . ($img ? '<meta property="og:image" content="' . esc_url($img) . '" />' : '')
                   . '<meta name="twitter:card" content="summary" />';
        }
        return preg_replace('~</head>~i', $head . '</head>', $html, 1);
    });
}, -100);

/* ---------- v1.7: safe HTTP -> HTTPS redirect ----------
 * The site answers on plain http:// too, so Google sees two copies of every page.
 * The redirect stays OFF until the scheme check below has been verified, then it is switched on
 * with POST /wp-json/mc/v1/https {"enable":true} (admins only). A request that already looks like
 * HTTPS from any signal (is_ssl, port 443, X-Forwarded-Proto/SSL, CF-Visitor) is never redirected,
 * so a proxy in front of the site cannot cause a redirect loop.
 */
function mc_request_is_https() {
    if (is_ssl()) return true;
    $s = $_SERVER;
    if (($s['SERVER_PORT'] ?? '') == '443') return true;
    if (strtolower($s['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
    if (strtolower($s['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') return true;
    if (strpos($s['HTTP_CF_VISITOR'] ?? '', 'https') !== false) return true;
    if (strtolower($s['REQUEST_SCHEME'] ?? '') === 'https') return true;
    return false;
}
add_action('init', function () {
    if (!get_option('mc_force_https') || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) || php_sapi_name() === 'cli') return;
    if (mc_request_is_https()) return;
    $host = strtolower(preg_replace('~:\d+$~', '', $_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== 'meftacard.com' && $host !== 'www.meftacard.com') return;
    wp_safe_redirect('https://meftacard.com' . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
    exit;
}, 0);
add_action('rest_api_init', function () {
    register_rest_route('mc/v1', '/scheme', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function () {
            $s = $_SERVER;
            return ['looks_https' => mc_request_is_https(), 'is_ssl' => is_ssl(), 'port' => $s['SERVER_PORT'] ?? null,
                    'xfp' => $s['HTTP_X_FORWARDED_PROTO'] ?? null, 'xfssl' => $s['HTTP_X_FORWARDED_SSL'] ?? null,
                    'cf' => $s['HTTP_CF_VISITOR'] ?? null, 'scheme' => $s['REQUEST_SCHEME'] ?? null,
                    'force_https' => (bool) get_option('mc_force_https')];
        }]);
    register_rest_route('mc/v1', '/https', [
        'methods' => 'POST', 'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($r) { update_option('mc_force_https', !empty($r['enable']) ? 1 : 0); return ['force_https' => (bool) get_option('mc_force_https')]; }]);
});
