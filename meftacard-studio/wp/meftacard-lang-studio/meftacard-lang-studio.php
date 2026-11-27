<?php
/**
 * Plugin Name: MEFTACARD Lang & Studio
 * Description: Correct html lang/dir per post language, hreflang for translated posts, full-HTML Studio landing pages, and a Studio sitemap (/studio-sitemap.xml).
 * Version: 1.9.0
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

/* ---------- v1.9: article layout for Studio articles (categories 41/42) ----------
 * The theme prints single posts edge to edge with a bare header and footer.
 * For Studio articles we add: centred reading column, header with Studio links and a
 * free-review button, breadcrumb + updated date + reading time, a table of contents,
 * a fixed-price package box, a richer footer and a mobile contact bar.
 * The cover image (which only repeats the H1) is removed from the page; it stays the
 * featured/OG image. No phone number is printed anywhere.
 */
function mc_article_strings($lang) {
    if ($lang === 'ar') return [
        'home' => 'الرئيسية', 'studio' => 'MEFTACARD Studio', 'hub' => home_url('/ar/studio/'),
        'review' => home_url('/ar/studio/free-website-review/'), 'review_t' => 'مراجعة مجانية لموقعك',
        'packages' => 'الباقات والأسعار', 'services' => 'الخدمات', 'articles' => 'المقالات',
        'updated' => 'آخر تحديث', 'read' => 'دقائق قراءة', 'by' => 'فريق MEFTACARD Studio',
        'toc' => 'محتويات المقال',
        'box_t' => 'أسعار ثابتة ونطاق عمل مكتوب قبل البدء',
        'box_p' => 'ثلاث باقات واضحة، والدفع بعملة تيثر (USDT): نصف عند البدء ونصف عند الإطلاق. الكود والدومين والاستضافة باسمك من اليوم الأول.',
        'p' => [
            ['المحلية', '1,250 دولار', 'موقع بالعربية والإنجليزية حتى 6 صفحات، وملف Google التجاري، وسيو لـ 10 كلمات محلية، وأزرار واتساب واتصال. الإطلاق خلال 14 يوماً ودعم 30 يوماً.'],
            ['التجارة والتصدير', '2,250 دولار', 'كتالوج منتجات ولغة إضافية، وسيو لـ 25 كلمة للتصدير، ونموذج طلب عرض سعر إلى واتساب والبريد، وتدريب فريقك حضورياً. الإطلاق خلال 30 يوماً ودعم 60 يوماً.'],
            ['المؤسسات', 'من 25,000 دولار', 'منصة مؤسسية وتطبيق جوال ووكيل ذكاء اصطناعي على واتساب والموقع ودعم على مدار الساعة. من 12 إلى 16 أسبوعاً، والدفع حسب المراحل المعتمدة.'],
        ],
        'ask' => 'اسأل عن هذه الباقة', 'see' => 'تفاصيل كل الباقات',
        'end_t' => 'هل تريد معرفة ما ينقص موقعك الحالي؟',
        'end_p' => 'نراجع موقعك مجاناً ونرسل لك خلال 48 ساعة قائمة بالمشكلات وأولويات الإصلاح، دون أي التزام.',
        'tg' => 'راسلنا على تيليغرام', 'tg_msg' => 'السلام عليكم، قرأت مقالكم وأريد عرض سعر لموقعي',
        'bar_review' => 'مراجعة مجانية', 'bar_tg' => 'تيليغرام',
        'f_services' => [['تصميم المواقع', '/ar/studio/website-design/'], ['السيو', '/ar/studio/seo-services/'], ['دعم المواقع', '/ar/studio/website-support/'], ['وكلاء الذكاء الاصطناعي', '/ar/studio/ai-agents/'], ['تصميم التطبيقات', '/ar/studio/app-design/']],
        'f_countries' => [['العراق', '/ar/studio/iraq/'], ['عُمان', '/ar/studio/oman/'], ['الإمارات', '/ar/studio/uae/'], ['السعودية', '/ar/studio/saudi-arabia/'], ['قطر', '/ar/studio/qatar/'], ['الكويت', '/ar/studio/kuwait/']],
        'f_contact' => 'تواصل', 'f_note' => 'تصميم مواقع وسيو ووكلاء ذكاء اصطناعي، أونلاين بالكامل، والدفع بعملة تيثر (USDT).',
    ];
    return [
        'home' => 'Home', 'studio' => 'MEFTACARD Studio', 'hub' => home_url('/studio/'),
        'review' => home_url('/studio/free-website-review/'), 'review_t' => 'Free website review',
        'packages' => 'Packages & prices', 'services' => 'Services', 'articles' => 'Articles',
        'updated' => 'Updated', 'read' => 'min read', 'by' => 'MEFTACARD Studio team',
        'toc' => 'In this article',
        'box_t' => 'Fixed prices and a written scope before work starts',
        'box_p' => 'Three clear packages, paid in Tether (USDT): half at start, half at launch. Code, domain and hosting are in your name from day one.',
        'p' => [
            ['Local', '$1,250', 'Arabic/English site up to 6 pages, Google Business Profile, SEO for 10 local keywords, WhatsApp and call buttons. Launch in 14 days, 30 days of support.'],
            ['Trade & Export', '$2,250', 'Product catalog plus an extra language, SEO for 25 export keywords, quote-request form to WhatsApp and email, on-site team training. Launch in 30 days, 60 days of support.'],
            ['Enterprise', 'from $25,000', 'Enterprise platform, mobile app, AI agent on WhatsApp and web, 24/7 support. 12–16 weeks, paid per approved milestone.'],
        ],
        'ask' => 'Ask about this package', 'see' => 'See all package details',
        'end_t' => 'Want to know what your current site is missing?',
        'end_p' => 'We review your site for free and send you a prioritised list of issues within 48 hours. No commitment.',
        'tg' => 'Message us on Telegram', 'tg_msg' => 'Hello, I read your article and would like a quote for my website',
        'bar_review' => 'Free review', 'bar_tg' => 'Telegram',
        'f_services' => [['Website design', '/studio/website-design/'], ['SEO', '/studio/seo-services/'], ['Website support', '/studio/website-support/'], ['AI agents', '/studio/ai-agents/'], ['App design', '/studio/app-design/']],
        'f_countries' => [['Iraq', '/studio/iraq/'], ['Oman', '/studio/oman/'], ['UAE', '/studio/uae/'], ['Saudi Arabia', '/studio/saudi-arabia/'], ['Qatar', '/studio/qatar/'], ['Kuwait', '/studio/kuwait/']],
        'f_contact' => 'Contact', 'f_note' => 'Websites, SEO and AI agents, fully online, paid in Tether (USDT).',
    ];
}

function mc_is_studio_article() {
    return is_singular('post') && has_category(MC_ARTICLE_CATS, get_queried_object_id());
}

function mc_article_slug($text) {
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(wp_strip_all_tags($text)));
    return trim(mb_substr($s, 0, 60), '-');
}

function mc_article_transform($html) {
    $id   = get_queried_object_id();
    $lang = mc_post_lang($id) === 'ar' ? 'ar' : 'en';
    $t    = mc_article_strings($lang);
    $e    = 'esc_html';
    $tg   = 'https://t.me/meftacard?text=' . rawurlencode($t['tg_msg']);

    // 1. drop the cover figure (it repeats the H1); it stays the featured / OG image
    $html = preg_replace('#(<p>)?\s*<!--mc-cover-->.*?<!--/mc-cover-->\s*(</p>)?#s', '', $html, 1);

    // 2. header: Studio links + free-review button
    $nav = '<nav class="mc-hnav" aria-label="Studio"><a href="' . esc_url($t['hub']) . '">' . $e($t['studio']) . '</a>'
         . '<a href="' . esc_url($t['hub'] . '#packages') . '">' . $e($t['packages']) . '</a>'
         . '<a class="mc-hcta" href="' . esc_url($t['review']) . '">' . $e($t['review_t']) . '</a></nav>';
    $html = preg_replace('#(<header class="top"><div class="wrap nav"><a class="brand".*?</a>)#s', '$1' . $nav, $html, 1);

    // 3. hero: breadcrumb, H1, updated date, reading time
    $content = get_post_field('post_content', $id);
    $words   = count(preg_split('/\s+/u', trim(wp_strip_all_tags($content)), -1, PREG_SPLIT_NO_EMPTY));
    $mins    = max(1, (int) round($words / 200));
    $date    = get_the_modified_date('Y-m-d', $id);
    $html = preg_replace_callback('#<article><h1 class="entry-title">(.*?)</h1>#s', function ($m) use ($t, $e, $mins, $date) {
        return '<article class="mc-art"><header class="mc-hero">'
            . '<nav class="mc-bc" aria-label="breadcrumb"><a href="' . esc_url(home_url('/')) . '">' . $e($t['home']) . '</a><span>/</span>'
            . '<a href="' . esc_url($t['hub']) . '">' . $e($t['studio']) . '</a><span>/</span><span>' . $e($t['articles']) . '</span></nav>'
            . '<h1 class="entry-title">' . $m[1] . '</h1>'
            . '<p class="mc-meta"><span>' . $e($t['by']) . '</span><span>' . $e($t['updated']) . ': <time datetime="' . esc_attr($date) . '" dir="ltr">' . $e($date) . '</time></span><span>' . $mins . ' ' . $e($t['read']) . '</span></p>'
            . '</header>';
    }, $html, 1);

    // 4. ids on H2s + table of contents before the first H2
    $toc = [];
    $seen = [];
    $html = preg_replace_callback('#<h2>(.*?)</h2>#s', function ($m) use (&$toc, &$seen) {
        $slug = mc_article_slug($m[1]) ?: 's';
        $base = $slug; $i = 2;
        while (isset($seen[$slug])) $slug = $base . '-' . $i++;
        $seen[$slug] = 1;
        $toc[] = [$slug, wp_strip_all_tags($m[1])];
        return '<h2 id="' . esc_attr($slug) . '">' . $m[1] . '</h2>';
    }, $html);
    // the last H2 is the country list; leave it out of the contents
    $items = array_slice($toc, 0, max(0, count($toc) - 1));
    if (count($items) >= 3) {
        $li = '';
        foreach ($items as $it) $li .= '<li><a href="#' . esc_attr($it[0]) . '">' . esc_html($it[1]) . '</a></li>';
        $box = '<details class="mc-toc"><summary>' . $e($t['toc']) . ' <span>(' . count($items) . ')</span></summary><ol>' . $li . '</ol></details>';
        $html = preg_replace('#<h2 id=#', $box . '<h2 id=', $html, 1);
    }

    // 5. package box before the middle H2
    $cards = '';
    foreach ($t['p'] as $p) {
        $msg = $t['tg_msg'] . ' — ' . $p[0];
        $cards .= '<div class="mc-pk"><h3>' . $e($p[0]) . '</h3><p class="mc-pk-price">' . $e($p[1]) . '</p><p>' . $e($p[2]) . '</p>'
                . '<a href="' . esc_url('https://t.me/meftacard?text=' . rawurlencode($msg)) . '" target="_blank" rel="noopener">' . $e($t['ask']) . '</a></div>';
    }
    $offer = '<aside class="mc-offer"><p class="mc-offer-t">' . $e($t['box_t']) . '</p><p>' . $e($t['box_p']) . '</p><div class="mc-pks">' . $cards . '</div>'
           . '<p class="mc-offer-more"><a href="' . esc_url($t['hub'] . '#packages') . '">' . $e($t['see']) . '</a></p></aside>';
    if (count($toc) >= 4) {
        $target = $toc[(int) floor(count($toc) / 2)][0];
        $html = str_replace('<h2 id="' . $target . '">', $offer . '<h2 id="' . $target . '">', $html);
    }

    // 6. closing call to action before the country list
    $end = '<aside class="mc-end"><p class="mc-end-t">' . $e($t['end_t']) . '</p><p>' . $e($t['end_p']) . '</p><p class="mc-end-btns">'
         . '<a class="mc-btn" href="' . esc_url($t['review']) . '">' . $e($t['review_t']) . '</a>'
         . '<a class="mc-btn mc-btn2" href="' . esc_url($tg) . '" target="_blank" rel="noopener">' . $e($t['tg']) . '</a></p></aside>';
    if ($toc) {
        $last = end($toc)[0];
        $html = preg_replace('#(<p>)?\s*<!--mc-countries-->\s*(</p>)?#', $end, $html, 1, $n);
        if (!$n) $html = str_replace('<h2 id="' . $last . '">', $end . '<h2 id="' . $last . '">', $html);
    }

    // 7. footer + mobile contact bar
    $col = function ($title, $links) { $o = '<div><p class="mc-f-t">' . esc_html($title) . '</p><ul>';
        foreach ($links as $l) $o .= '<li><a href="' . esc_url(home_url($l[1])) . '">' . esc_html($l[0]) . '</a></li>';
        return $o . '</ul></div>'; };
    $footer = '<footer class="mc-foot"><div class="wrap"><div class="mc-f-grid"><div><p class="mc-f-t">' . $e($t['studio']) . '</p><p>' . $e($t['f_note']) . '</p></div>'
        . $col($t['services'], $t['f_services']) . $col($lang === 'ar' ? 'الدول' : 'Countries', $t['f_countries'])
        . '<div><p class="mc-f-t">' . $e($t['f_contact']) . '</p><ul><li><a href="https://t.me/meftacard" target="_blank" rel="noopener">Telegram @meftacard</a></li>'
        . '<li><a href="' . esc_url($t['review']) . '">' . $e($t['review_t']) . '</a></li><li><a href="https://t.me/meftacardchannel" target="_blank" rel="noopener">t.me/meftacardchannel</a></li></ul></div></div>'
        . '<p class="mc-f-copy"><bdi>MEFTACARD</bdi> © ' . gmdate('Y') . '</p></div></footer>'
        . '<div class="mc-bar"><a href="' . esc_url($t['review']) . '">' . $e($t['bar_review']) . '</a><a class="mc-bar-tg" href="' . esc_url($tg) . '" target="_blank" rel="noopener">' . $e($t['bar_tg']) . '</a></div>';
    $html = preg_replace('#<footer><div class="wrap"><bdi>MeftaCard</bdi>.*?</footer>#s', $footer, $html, 1);

    return $html;
}

// Started at priority 0, so this buffer wraps the v1.2 buffer (priority 1). Inner buffers
// flush first, so the title is already an <h1> when this callback runs.
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || !mc_is_studio_article()) return;
    ob_start(function ($html) {
        if (stripos($html, '<html') === false) return $html;
        return mc_article_transform($html);
    });
}, 0);

add_action('wp_head', function () {
    if (!mc_is_studio_article()) return;
    echo '<style id="mc-article">'
    . 'body.single main{width:min(760px,92%);margin:0 auto;padding:22px 0 56px}'
    . '.mc-art{font-size:17.5px;line-height:1.95}.mc-art p{margin:0 0 1.05em}.mc-art a{color:#0d6b52;text-decoration:underline;text-underline-offset:3px}'
    . '.mc-hero{padding:6px 0 18px;border-bottom:1px solid var(--line,#cdd3c9);margin-bottom:22px}'
    . '.mc-bc{font-size:.85rem;opacity:.75;display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}.mc-bc a{color:inherit!important;text-decoration:none!important}'
    . 'article.mc-art>header h1.entry-title{font-size:clamp(28px,4.6vw,44px);line-height:1.4;margin:0 0 12px;letter-spacing:normal}'
    . '.mc-meta{display:flex;flex-wrap:wrap;gap:6px 18px;font-size:.88rem;opacity:.75;margin:0!important}'
    . '.mc-art h2{font-size:clamp(22px,3vw,30px);line-height:1.5;margin:1.9em 0 .6em;scroll-margin-top:16px}.mc-art h3{font-size:1.15em;margin:1.4em 0 .5em}'
    . '.mc-art ul,.mc-art ol{padding-inline-start:1.3em;margin:0 0 1.1em}.mc-art li{margin:.3em 0}'
    . '.mc-art figure{margin:1.6em 0}.mc-art img{max-width:100%;height:auto;border-radius:14px}.mc-art figcaption{font-size:.85rem;opacity:.7;margin-top:6px}'
    . '.mc-art video{max-width:100%;height:auto;border-radius:14px}'
    . '.mc-art table{width:100%;border-collapse:collapse;font-size:.92rem;line-height:1.6;display:block;overflow-x:auto}.mc-art th,.mc-art td{border:1px solid var(--line,#cdd3c9);padding:9px 11px;text-align:start;vertical-align:top}.mc-art th{background:#e9eee6}'
    . '.mc-art blockquote{margin:1.4em 0;padding:12px 18px;border-inline-start:4px solid #2fd3a2;background:#fff;border-radius:10px}'
    . '.mc-art hr{border:0;border-top:1px solid var(--line,#cdd3c9);margin:2em 0}'
    . '.mc-toc{background:#fff;border:1px solid var(--line,#cdd3c9);border-radius:14px;padding:14px 18px;margin:1.6em 0}.mc-toc summary{font-weight:700}.mc-toc summary span{opacity:.6;font-weight:400}.mc-toc ol{margin:12px 0 0;font-size:.95rem;line-height:1.7}.mc-toc a{text-decoration:none!important}'
    . '.mc-offer{background:var(--navy,#0b1b2a);color:#e8f2ee;border-radius:20px;padding:24px;margin:2.2em 0}.mc-offer-t,.mc-end-t{font-size:1.3em;font-weight:800;line-height:1.5;margin-bottom:6px!important}.mc-offer>p{color:#cfe0dc}'
    . '.mc-pks{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0 8px}.mc-pk{background:#112a3a;border:1px solid #2c4c58;border-radius:14px;padding:16px;display:flex;flex-direction:column;font-size:.92rem;line-height:1.7}'
    . '.mc-pk h3{margin:0 0 4px!important;font-size:1.05rem;color:#fff}.mc-pk-price{font-size:1.35rem;font-weight:800;color:var(--mint,#a7f2d1);margin:0 0 8px!important}.mc-pk p{color:#cfe0dc}'
    . '.mc-pk a{margin-top:auto;display:block;text-align:center;background:var(--mint,#a7f2d1);color:#10312b!important;text-decoration:none!important;border-radius:30px;padding:8px 12px;font-weight:700}'
    . '.mc-offer-more{margin:10px 0 0!important}.mc-offer-more a{color:var(--mint,#a7f2d1)!important}'
    . '.mc-end{background:#fff;border:1px solid var(--line,#cdd3c9);border-radius:20px;padding:24px;margin:2.2em 0}.mc-end-btns{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0 0!important}'
    . '.mc-btn{display:inline-block;background:var(--navy,#0b1b2a);color:#fff!important;text-decoration:none!important;border-radius:30px;padding:10px 20px;font-weight:700}.mc-btn2{background:#fff;color:var(--navy,#0b1b2a)!important;border:1px solid var(--navy,#0b1b2a)}'
    . '.mc-hnav{display:flex;align-items:center;gap:22px;font-size:.92rem}.mc-hnav a{color:#d5e5e7}.mc-hnav .mc-hcta{background:var(--mint,#a7f2d1);color:#10312b;border-radius:30px;padding:8px 16px;font-weight:700}'
    . '.mc-foot{background:var(--navy,#0b1b2a);color:#bed7ce}.mc-foot .wrap{display:block!important;padding:36px 0 90px}.mc-f-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:24px}.mc-f-t{color:#fff;font-weight:700;margin-bottom:8px!important}.mc-foot ul{list-style:none;margin:0;padding:0;line-height:2}.mc-foot a:hover{text-decoration:underline}.mc-f-copy{margin-top:24px!important;font-size:.85rem;opacity:.7}'
    . '.mc-bar{display:none}'
    . '@media (max-width:760px){.mc-pks{grid-template-columns:1fr}.mc-f-grid{grid-template-columns:1fr 1fr}.mc-hnav a:not(.mc-hcta){display:none}.mc-hnav .mc-hcta{padding:7px 13px;font-size:.85rem}.mc-art{font-size:17px;line-height:1.9}'
    . '.mc-bar{display:flex;position:fixed;inset:auto 0 0 0;z-index:50;gap:8px;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:#0b1b2af2;backdrop-filter:blur(6px)}.mc-bar a{flex:1;text-align:center;border-radius:30px;padding:10px;font-weight:700;background:var(--mint,#a7f2d1);color:#10312b}.mc-bar .mc-bar-tg{background:#fff}}'
    . '@media (max-width:420px){.mc-hnav .mc-hcta{display:none}.mc-f-grid{grid-template-columns:1fr}}'
    . '</style>' . "\n";
}, 60);
