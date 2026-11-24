<?php
/**
 * Plugin Name: MEFTACARD Gifts Bot
 * Description: Telegram bot that sells Telegram gifts (NFTs) from MRKT with a fixed markup, takes payment in USDT (BEP20) with automatic on-chain confirmation, and notifies admins to deliver. Settings → MEFTACARD Gifts Bot.
 * Version: 0.1.0
 * Author: MEFTACARD
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

const MGB_VER = '0.1.0';
const MGB_USDT_BSC = '0x55d398326f99059ff775485246999027b3197955';
const MGB_TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
const MGB_RPC = ['https://bsc-rpc.publicnode.com', 'https://bsc-dataseed.binance.org', 'https://bsc.drpc.org'];

/* ------------------------------------------------------------------ settings */
function mgb_opt($k, $d = '') {
    $o = get_option('mgb_settings', []);
    return isset($o[$k]) && $o[$k] !== '' ? $o[$k] : $d;
}
function mgb_defaults() {
    return ['token' => '', 'secret' => wp_generate_password(32, false), 'admins' => '', 'markup' => '5', 'min_profit' => '1',
            'fixed_fee' => '0.5', 'fx_buffer' => '1', 'address' => '', 'quote_minutes' => '20', 'mrkt_token' => '',
            'collections' => '', 'support' => '@meftacard'];
}
register_activation_hook(__FILE__, function () {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = $wpdb->prefix . 'mgb_orders';
    dbDelta("CREATE TABLE $t (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        chat_id bigint NOT NULL,
        username varchar(64) DEFAULT '' NOT NULL,
        lang varchar(4) DEFAULT 'fa' NOT NULL,
        item_id varchar(128) DEFAULT '' NOT NULL,
        item_title varchar(255) DEFAULT '' NOT NULL,
        item_link varchar(255) DEFAULT '' NOT NULL,
        recipient varchar(64) DEFAULT '' NOT NULL,
        cost_ton decimal(20,9) DEFAULT 0 NOT NULL,
        cost_usd decimal(20,6) DEFAULT 0 NOT NULL,
        price_usd decimal(20,6) DEFAULT 0 NOT NULL,
        pay_amount decimal(20,6) DEFAULT 0 NOT NULL,
        from_block bigint unsigned DEFAULT 0 NOT NULL,
        tx_hash varchar(80) DEFAULT '' NOT NULL,
        status varchar(20) DEFAULT 'quote' NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NULL,
        paid_at datetime NULL,
        done_at datetime NULL,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY chat_id (chat_id),
        KEY tx_hash (tx_hash)
    ) {$wpdb->get_charset_collate()};");
    $o = get_option('mgb_settings', []);
    update_option('mgb_settings', array_merge(mgb_defaults(), is_array($o) ? array_filter($o, 'strlen') : []), false);
    if (!wp_next_scheduled('mgb_check_payments')) wp_schedule_event(time() + 60, 'mgb_minute', 'mgb_check_payments');
});
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('mgb_check_payments'); });
add_filter('cron_schedules', function ($s) { $s['mgb_minute'] = ['interval' => 60, 'display' => 'Every minute (MGB)']; return $s; });

add_action('admin_menu', function () {
    add_options_page('MEFTACARD Gifts Bot', 'MEFTACARD Gifts Bot', 'manage_options', 'mgb', 'mgb_settings_page');
});
add_action('admin_init', function () {
    register_setting('mgb', 'mgb_settings', ['sanitize_callback' => function ($in) {
        $old = get_option('mgb_settings', []);
        $out = array_merge(mgb_defaults(), is_array($old) ? $old : []);
        foreach (['admins', 'collections', 'support'] as $k) if (isset($in[$k])) $out[$k] = sanitize_textarea_field($in[$k]);
        foreach (['markup', 'min_profit', 'fixed_fee', 'fx_buffer', 'quote_minutes'] as $k) if (isset($in[$k])) $out[$k] = (string) max(0, (float) $in[$k]);
        if (isset($in['address'])) { $a = strtolower(trim($in['address'])); $out['address'] = preg_match('/^0x[0-9a-f]{40}$/', $a) ? $a : ''; }
        foreach (['token', 'mrkt_token'] as $k) if (!empty($in[$k])) $out[$k] = trim(sanitize_text_field($in[$k])); // empty = keep current
        return $out;
    }]);
});
function mgb_settings_page() {
    if (!current_user_can('manage_options')) return;
    $o = array_merge(mgb_defaults(), get_option('mgb_settings', []));
    $f = function ($k, $label, $help = '', $type = 'text') use ($o) {
        $secret = in_array($k, ['token', 'mrkt_token'], true);
        $val = $secret ? '' : $o[$k];
        $ph = $secret && $o[$k] ? 'ذخیره شده — برای تغییر، مقدار جدید را وارد کنید' : '';
        echo '<tr><th><label for="mgb_' . esc_attr($k) . '">' . esc_html($label) . '</label></th><td>';
        if ($type === 'textarea') echo '<textarea id="mgb_' . esc_attr($k) . '" name="mgb_settings[' . esc_attr($k) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea>';
        else echo '<input id="mgb_' . esc_attr($k) . '" type="' . ($secret ? 'password' : 'text') . '" name="mgb_settings[' . esc_attr($k) . ']" value="' . esc_attr($val) . '" placeholder="' . esc_attr($ph) . '" class="regular-text" autocomplete="off">';
        if ($help) echo '<p class="description">' . esc_html($help) . '</p>';
        echo '</td></tr>';
    };
    echo '<div class="wrap" dir="rtl"><h1>MEFTACARD Gifts Bot</h1><form method="post" action="options.php">';
    settings_fields('mgb');
    echo '<table class="form-table">';
    $f('token', 'توکن ربات تلگرام', 'از @BotFather. فقط اینجا وارد کنید، هرگز در چت نفرستید.');
    $f('admins', 'Chat ID مدیرها', 'هر کدام در یک خط. برای پیدا کردن Chat ID خودتان، به ربات /myid بفرستید.', 'textarea');
    $f('address', 'آدرس کیف پول USDT (BEP20)', 'آدرسی که مشتری‌ها به آن پرداخت می‌کنند (شروع با 0x).');
    $f('markup', 'درصد سود', 'پیش‌فرض ۵');
    $f('min_profit', 'حداقل سود هر سفارش (دلار)', 'برای گیفت‌های ارزان؛ پیش‌فرض ۱ دلار');
    $f('fixed_fee', 'هزینه ثابت هر سفارش (دلار)', 'کارمزد انتقال گیفت و شبکه؛ پیش‌فرض ۰٫۵');
    $f('fx_buffer', 'حاشیه نوسان TON (درصد)', 'برای پوشش تغییر قیمت TON تا زمان خرید؛ پیش‌فرض ۱');
    $f('quote_minutes', 'اعتبار قیمت (دقیقه)', 'پیش‌فرض ۲۰');
    $f('collections', 'کالکشن‌های نمایش داده‌شده', 'نام کالکشن‌ها، هر کدام در یک خط (مثلاً Plush Pepe). خالی = پرفروش‌ترین‌ها از MRKT.', 'textarea');
    $f('mrkt_token', 'توکن MRKT', 'برای خواندن قیمت‌ها از MRKT. اختیاری؛ بدون آن، قیمت را دستی با دستور /quote می‌دهید.');
    $f('support', 'آیدی پشتیبانی', 'پیش‌فرض @meftacard');
    echo '</table>';
    submit_button('ذخیره');
    echo '</form>';
    $hook = rest_url('mgb/v1/hook');
    echo '<h2>راه‌اندازی</h2><ol><li>توکن ربات را ذخیره کنید.</li><li>روی دکمه زیر بزنید تا ربات به سایت وصل شود.</li></ol>';
    echo '<form method="post"><input type="hidden" name="mgb_setup" value="1">' . wp_nonce_field('mgb_setup', '_mgbn', true, false);
    submit_button('اتصال ربات به سایت (setWebhook)', 'secondary');
    echo '</form>';
    if (!empty($_POST['mgb_setup']) && check_admin_referer('mgb_setup', '_mgbn')) {
        $r = mgb_setup_webhook();
        echo '<div class="notice notice-' . ($r['ok'] ? 'success' : 'error') . '"><p>' . esc_html($r['msg']) . '</p></div>';
    }
    echo '<h2>وضعیت</h2><pre style="direction:ltr;text-align:left;background:#fff;padding:12px">' . esc_html(wp_json_encode(mgb_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    echo '<p>آدرس webhook: <code>' . esc_html($hook) . '</code></p></div>';
}

/* ------------------------------------------------------------------ http helpers */
function mgb_tg($method, $args = []) {
    $token = mgb_opt('token');
    if (!$token) return null;
    $r = wp_remote_post("https://api.telegram.org/bot{$token}/{$method}", ['timeout' => 15, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode($args)]);
    if (is_wp_error($r)) return null;
    return json_decode(wp_remote_retrieve_body($r), true);
}
function mgb_send($chat, $text, $kb = null) {
    $a = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($kb) $a['reply_markup'] = ['inline_keyboard' => $kb];
    return mgb_tg('sendMessage', $a);
}
function mgb_rpc($method, $params) {
    foreach (MGB_RPC as $url) {
        $r = wp_remote_post($url, ['timeout' => 12, 'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])]);
        if (is_wp_error($r)) continue;
        $j = json_decode(wp_remote_retrieve_body($r), true);
        if (isset($j['result'])) return $j['result'];
    }
    return null;
}
function mgb_ton_usd() {
    $c = get_transient('mgb_ton_usd');
    if ($c) return (float) $c;
    $p = 0;
    $r = wp_remote_get('https://tonapi.io/v2/rates?tokens=ton&currencies=usd', ['timeout' => 10]);
    if (!is_wp_error($r)) { $j = json_decode(wp_remote_retrieve_body($r), true); $p = (float) ($j['rates']['TON']['prices']['USD'] ?? 0); }
    if ($p <= 0) { // fallback: Nobitex (reachable from Iranian servers)
        $r = wp_remote_get('https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=usdt', ['timeout' => 10]);
        if (!is_wp_error($r)) { $j = json_decode(wp_remote_retrieve_body($r), true); $p = (float) ($j['stats']['ton-usdt']['latest'] ?? 0); }
    }
    if ($p > 0) set_transient('mgb_ton_usd', $p, 120);
    return $p;
}

/* ------------------------------------------------------------------ MRKT catalog (read-only) */
function mgb_mrkt($path, $body = null) {
    $tok = mgb_opt('mrkt_token');
    if (!$tok) return null;
    $args = ['timeout' => 15, 'headers' => ['Authorization' => $tok, 'Content-Type' => 'application/json', 'Accept' => 'application/json']];
    $r = $body === null ? wp_remote_get('https://api.tgmrkt.io/api/v1/' . $path, $args)
                        : wp_remote_post('https://api.tgmrkt.io/api/v1/' . $path, $args + ['body' => wp_json_encode($body)]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return null;
    return json_decode(wp_remote_retrieve_body($r), true);
}
function mgb_nano($v) { return is_numeric($v) ? ((float) $v > 100000 ? (float) $v / 1e9 : (float) $v) : 0; }
/** Cheapest listings of one collection: [[id, title, link, price_ton], ...] */
function mgb_listings($collection, $n = 5) {
    $key = 'mgb_l_' . md5($collection);
    if ($c = get_transient($key)) return $c;
    $j = mgb_mrkt('gifts/saling', ['collectionNames' => [$collection], 'modelNames' => [], 'backdropNames' => [], 'symbolNames' => [],
        'ordering' => 'Price', 'lowToHigh' => true, 'count' => $n, 'cursor' => '', 'query' => null, 'promotedFirst' => false]);
    $out = [];
    foreach ((array) ($j['gifts'] ?? []) as $g) {
        $price = mgb_nano($g['salePrice'] ?? $g['price'] ?? 0);
        if ($price <= 0) continue;
        $title = trim(($g['title'] ?? $g['collectionTitle'] ?? $collection) . ' #' . ($g['number'] ?? ''));
        $model = $g['modelTitle'] ?? $g['modelName'] ?? '';
        $slug = preg_replace('/[^A-Za-z0-9]/', '', $g['name'] ?? $collection);
        $out[] = [(string) ($g['id'] ?? ''), $title . ($model ? " · $model" : ''), $slug && isset($g['number']) ? "https://t.me/nft/{$slug}-{$g['number']}" : '', $price];
    }
    if ($out) set_transient($key, $out, 90);
    return $out;
}
function mgb_collections() {
    $fixed = array_values(array_filter(array_map('trim', explode("\n", (string) mgb_opt('collections')))));
    if ($fixed) return $fixed;
    $c = get_transient('mgb_cols');
    if ($c) return $c;
    $j = mgb_mrkt('gifts/collections');
    $out = [];
    foreach ((array) $j as $row) if (is_array($row) && !empty($row['title'] ?? $row['name'] ?? '')) $out[] = $row['title'] ?? $row['name'];
    $out = array_slice($out, 0, 12);
    if ($out) set_transient('mgb_cols', $out, 600);
    return $out;
}

/* ------------------------------------------------------------------ pricing */
/** Customer price in USD for a cost in TON. Returns [cost_usd, price_usd] or null. */
function mgb_price($ton) {
    $rate = mgb_ton_usd();
    if ($rate <= 0 || $ton <= 0) return null;
    $cost = $ton * $rate * (1 + (float) mgb_opt('fx_buffer', 1) / 100) + (float) mgb_opt('fixed_fee', 0.5);
    $price = max($cost * (1 + (float) mgb_opt('markup', 5) / 100), $cost + (float) mgb_opt('min_profit', 1));
    return [round($cost, 2), ceil($price * 100) / 100];
}
/** A unique payable amount (adds 0.001–0.099 so each pending order can be matched on-chain). */
function mgb_unique_amount($price) {
    global $wpdb;
    $t = $wpdb->prefix . 'mgb_orders';
    for ($i = 0; $i < 50; $i++) {
        $a = round($price + wp_rand(1, 99) / 1000, 3);
        $dup = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='awaiting' AND ABS(pay_amount-%f)<0.0005", $a));
        if (!$dup) return $a;
    }
    return round($price + wp_rand(100, 999) / 10000, 4);
}

/* ------------------------------------------------------------------ texts */
function mgb_t($lang, $k, ...$a) {
    static $T = null;
    if ($T === null) $T = [
        'fa' => [
            'menu' => "سلام 👋 به فروشگاه گیفت تلگرام MEFTACARD خوش آمدید.\nگیفت‌های NFT تلگرام را اینجا با تتر (USDT) بخرید؛ پرداخت خودکار تأیید می‌شود.",
            'b_buy' => '🎁 خرید گیفت', 'b_link' => '🔗 گیفت مشخصی می‌خواهم', 'b_orders' => '📦 سفارش‌های من', 'b_support' => '💬 پشتیبانی',
            'cols' => 'یک کالکشن را انتخاب کنید:', 'nocat' => "کاتالوگ الان در دسترس نیست.\nلینک گیفتی را که می‌خواهید بفرستید (مثلاً t.me/nft/PlushPepe-123) تا قیمتش را بدهیم.",
            'list' => 'ارزان‌ترین گیفت‌های %s (قیمت نهایی، با تتر):', 'empty' => 'الان گیفتی در این کالکشن پیدا نشد. کالکشن دیگری انتخاب کنید.',
            'ask_rcpt' => "گیفت به کدام حساب تلگرام فرستاده شود؟\nیوزرنیم را بفرستید (مثلاً @username) یا «خودم» را بزنید.", 'b_me' => '👤 خودم',
            'quote' => "🧾 سفارش #%d\n%s\nگیرنده: %s\n\n<b>مبلغ قابل پرداخت: %s USDT</b>\nشبکه: BEP20 (BSC)\nآدرس:\n<code>%s</code>\n\n⚠️ دقیقاً همین مبلغ را بفرستید (رقم‌های اعشار برای شناسایی پرداخت شماست).\nاین قیمت تا %d دقیقه معتبر است.",
            'b_paid' => '✅ پرداخت کردم', 'b_cancel' => '✖️ لغو',
            'notyet' => 'پرداخت هنوز روی شبکه دیده نشده. معمولاً ۱ تا ۳ دقیقه طول می‌کشد؛ کمی بعد دوباره بزنید.',
            'paid' => "✅ پرداخت سفارش #%d تأیید شد.\nگیفت به‌زودی به %s فرستاده می‌شود و همین‌جا خبرتان می‌کنیم.",
            'delivered' => "🎉 سفارش #%d تحویل شد. از خریدتان ممنونیم!", 'refunded' => 'سفارش #%d لغو و مبلغ بازپرداخت می‌شود. برای جزئیات به %s پیام دهید.',
            'expired' => 'اعتبار قیمت سفارش #%d تمام شد. اگر هنوز می‌خواهید، دوباره سفارش دهید.', 'cancelled' => 'سفارش لغو شد.',
            'link_ok' => "درخواست شما ثبت شد (سفارش #%d). قیمت را تا چند دقیقه دیگر همین‌جا می‌فرستیم.", 'badlink' => 'لینک معتبر نیست. لینک باید شبیه t.me/nft/Name-123 باشد.',
            'no_orders' => 'هنوز سفارشی ندارید.', 'support' => 'برای پشتیبانی به %s پیام دهید.', 'unavailable' => 'فروش موقتاً متوقف است؛ کمی بعد دوباره امتحان کنید.',
        ],
        'ar' => [
            'menu' => "مرحباً 👋 أهلاً بك في متجر هدايا تيليغرام من MEFTACARD.\nاشترِ هدايا NFT في تيليغرام بعملة تيثر (USDT) مع تأكيد تلقائي للدفع.",
            'b_buy' => '🎁 شراء هدية', 'b_link' => '🔗 أريد هدية محددة', 'b_orders' => '📦 طلباتي', 'b_support' => '💬 الدعم',
            'cols' => 'اختر مجموعة:', 'nocat' => "الكتالوج غير متاح الآن.\nأرسل رابط الهدية التي تريدها (مثل t.me/nft/PlushPepe-123) لنرسل لك السعر.",
            'list' => 'أرخص هدايا %s (السعر النهائي بالتيثر):', 'empty' => 'لا توجد هدايا في هذه المجموعة الآن. اختر مجموعة أخرى.',
            'ask_rcpt' => "إلى أي حساب تيليغرام نرسل الهدية؟\nأرسل اسم المستخدم (مثل @username) أو اضغط «حسابي».", 'b_me' => '👤 حسابي',
            'quote' => "🧾 الطلب #%d\n%s\nالمستلم: %s\n\n<b>المبلغ المطلوب: %s USDT</b>\nالشبكة: BEP20 (BSC)\nالعنوان:\n<code>%s</code>\n\n⚠️ أرسل هذا المبلغ بالضبط (الأرقام العشرية تحدد دفعتك).\nهذا السعر صالح لمدة %d دقيقة.",
            'b_paid' => '✅ دفعت', 'b_cancel' => '✖️ إلغاء',
            'notyet' => 'لم يظهر الدفع على الشبكة بعد. عادةً يستغرق 1 إلى 3 دقائق؛ حاول مرة أخرى بعد قليل.',
            'paid' => "✅ تم تأكيد دفع الطلب #%d.\nسنرسل الهدية قريباً إلى %s ونخبرك هنا.",
            'delivered' => "🎉 تم تسليم الطلب #%d. شكراً لشرائك!", 'refunded' => 'تم إلغاء الطلب #%d وسيُعاد المبلغ. للتفاصيل راسل %s.',
            'expired' => 'انتهت صلاحية سعر الطلب #%d. إذا ما زلت تريده، اطلب من جديد.', 'cancelled' => 'تم إلغاء الطلب.',
            'link_ok' => "تم تسجيل طلبك (الطلب #%d). سنرسل لك السعر هنا خلال دقائق.", 'badlink' => 'الرابط غير صالح. يجب أن يكون مثل t.me/nft/Name-123.',
            'no_orders' => 'لا توجد طلبات بعد.', 'support' => 'للدعم راسل %s.', 'unavailable' => 'البيع متوقف مؤقتاً؛ حاول بعد قليل.',
        ],
        'en' => [
            'menu' => "Hi 👋 Welcome to the MEFTACARD Telegram gift shop.\nBuy Telegram NFT gifts with USDT; payments are confirmed automatically.",
            'b_buy' => '🎁 Buy a gift', 'b_link' => '🔗 I want a specific gift', 'b_orders' => '📦 My orders', 'b_support' => '💬 Support',
            'cols' => 'Choose a collection:', 'nocat' => "The catalog isn't available right now.\nSend the link of the gift you want (e.g. t.me/nft/PlushPepe-123) and we'll quote it.",
            'list' => 'Cheapest %s gifts (final price in USDT):', 'empty' => 'No gifts in this collection right now. Choose another one.',
            'ask_rcpt' => "Which Telegram account should receive the gift?\nSend a username (e.g. @username) or tap “Me”.", 'b_me' => '👤 Me',
            'quote' => "🧾 Order #%d\n%s\nRecipient: %s\n\n<b>Amount to pay: %s USDT</b>\nNetwork: BEP20 (BSC)\nAddress:\n<code>%s</code>\n\n⚠️ Send exactly this amount (the decimals identify your payment).\nThis price is valid for %d minutes.",
            'b_paid' => '✅ I have paid', 'b_cancel' => '✖️ Cancel',
            'notyet' => "Payment isn't visible on the network yet. It usually takes 1–3 minutes; try again shortly.",
            'paid' => "✅ Payment for order #%d confirmed.\nThe gift will be sent to %s shortly and we'll let you know here.",
            'delivered' => "🎉 Order #%d delivered. Thank you!", 'refunded' => 'Order #%d was cancelled and will be refunded. Message %s for details.',
            'expired' => 'The price for order #%d has expired. Order again if you still want it.', 'cancelled' => 'Order cancelled.',
            'link_ok' => "Your request is in (order #%d). We'll send the price here within minutes.", 'badlink' => 'That link is not valid. It should look like t.me/nft/Name-123.',
            'no_orders' => 'No orders yet.', 'support' => 'For support, message %s.', 'unavailable' => 'Sales are paused right now; please try again shortly.',
        ],
    ];
    $s = $T[$lang][$k] ?? $T['en'][$k] ?? $k;
    return $a ? vsprintf($s, $a) : $s;
}
function mgb_lang($code) { $c = substr((string) $code, 0, 2); return in_array($c, ['fa', 'ar'], true) ? $c : ($c === 'ps' ? 'fa' : 'en'); }

/* ------------------------------------------------------------------ orders */
function mgb_table() { global $wpdb; return $wpdb->prefix . 'mgb_orders'; }
function mgb_order($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . mgb_table() . ' WHERE id=%d', $id), ARRAY_A); }
function mgb_admins() { return array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', (string) mgb_opt('admins'))))); }
function mgb_is_admin($chat) { return in_array((int) $chat, mgb_admins(), true); }
function mgb_notify_admins($text, $kb = null) { foreach (mgb_admins() as $a) mgb_send($a, $text, $kb); }

function mgb_create_quote($chat, $user, $lang, $item, $recipient) {
    global $wpdb;
    [$id, $title, $link, $ton] = $item;
    $p = mgb_price($ton);
    $addr = mgb_opt('address');
    if (!$p || !$addr) { mgb_send($chat, mgb_t($lang, 'unavailable')); return; }
    $block = mgb_rpc('eth_blockNumber', []);
    $amount = mgb_unique_amount($p[1]);
    $mins = (int) mgb_opt('quote_minutes', 20);
    $wpdb->insert(mgb_table(), ['chat_id' => $chat, 'username' => $user, 'lang' => $lang, 'item_id' => $id, 'item_title' => $title, 'item_link' => $link,
        'recipient' => $recipient, 'cost_ton' => $ton, 'cost_usd' => $p[0], 'price_usd' => $p[1], 'pay_amount' => $amount,
        'from_block' => $block ? max(0, hexdec($block) - 5) : 0, 'status' => 'awaiting', 'created_at' => current_time('mysql', true),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + $mins * 60)]);
    $oid = (int) $wpdb->insert_id;
    mgb_send($chat, mgb_t($lang, 'quote', $oid, esc_html($title), esc_html($recipient), number_format($amount, 3, '.', ''), $addr, $mins),
        [[['text' => mgb_t($lang, 'b_paid'), 'callback_data' => "paid:$oid"]], [['text' => mgb_t($lang, 'b_cancel'), 'callback_data' => "cancel:$oid"]]]);
}

/** Scan USDT transfers to our address since the order's block; mark matching orders paid. */
function mgb_check_payments($only = 0) {
    global $wpdb;
    $t = mgb_table();
    $wpdb->query("UPDATE $t SET status='expired' WHERE status='awaiting' AND expires_at < UTC_TIMESTAMP() - INTERVAL 30 MINUTE");
    $rows = $wpdb->get_results("SELECT * FROM $t WHERE status='awaiting'" . ($only ? $wpdb->prepare(' AND id=%d', $only) : ''), ARRAY_A);
    $addr = mgb_opt('address');
    if (!$rows || !$addr) return 0;
    $latest = mgb_rpc('eth_blockNumber', []);
    if (!$latest) return 0;
    $latest = hexdec($latest);
    $from = min(array_map(function ($r) { return (int) $r['from_block']; }, $rows)) ?: $latest - 900;
    $from = max($from, $latest - 20000);
    $topicTo = '0x' . str_repeat('0', 24) . substr($addr, 2);
    $found = 0;
    for ($b = $from; $b <= $latest; $b += 900) {
        $logs = mgb_rpc('eth_getLogs', [['fromBlock' => '0x' . dechex($b), 'toBlock' => '0x' . dechex(min($b + 899, $latest)),
            'address' => MGB_USDT_BSC, 'topics' => [MGB_TRANSFER_TOPIC, null, $topicTo]]]);
        foreach ((array) $logs as $lg) {
            $amt = mgb_wei_to_float($lg['data'] ?? '0x0');
            $tx = strtolower($lg['transactionHash'] ?? '');
            $blk = hexdec($lg['blockNumber'] ?? '0x0');
            if (!$tx || $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE tx_hash=%s", $tx))) continue;
            foreach ($rows as $k => $r) {
                if ($r['status'] !== 'awaiting' || $blk < (int) $r['from_block']) continue;
                if (abs($amt - (float) $r['pay_amount']) < 0.0005) {
                    $rows[$k]['status'] = 'paid';
                    if ($wpdb->update($t, ['status' => 'paid', 'tx_hash' => $tx, 'paid_at' => current_time('mysql', true)], ['id' => $r['id'], 'status' => 'awaiting'])) mgb_on_paid(mgb_order($r['id']));
                    $found++;
                    continue 2;
                }
            }
        }
    }
    return $found;
}
function mgb_wei_to_float($hex) {
    $hex = ltrim(substr($hex, 2), '0');
    if ($hex === '') return 0.0;
    if (function_exists('bcadd')) { $d = '0'; foreach (str_split($hex) as $c) $d = bcadd(bcmul($d, '16'), (string) hexdec($c)); return (float) bcdiv($d, '1000000000000000000', 6); }
    return hexdec($hex) / 1e18; // float fallback; exact enough for amounts below ~9e15 wei precision limits
}
add_action('mgb_check_payments', function () { mgb_check_payments(); });

function mgb_on_paid($o) {
    if (!$o) return;
    mgb_send($o['chat_id'], mgb_t($o['lang'], 'paid', $o['id'], esc_html($o['recipient'])));
    $msg = "💰 <b>پرداخت شد — سفارش #{$o['id']}</b>\nگیفت: " . esc_html($o['item_title']) . ($o['item_link'] ? "\n" . esc_html($o['item_link']) : '')
         . "\nگیرنده: " . esc_html($o['recipient']) . "\nخریدار: " . esc_html($o['username'] ?: (string) $o['chat_id'])
         . "\nدریافتی: {$o['pay_amount']} USDT · هزینه تخمینی: {$o['cost_usd']}$ ({$o['cost_ton']} TON)"
         . "\nسود تخمینی: " . number_format((float) $o['pay_amount'] - (float) $o['cost_usd'], 2) . "$\nTX: https://bscscan.com/tx/{$o['tx_hash']}";
    mgb_notify_admins($msg, [[['text' => '✅ تحویل شد', 'callback_data' => "done:{$o['id']}"], ['text' => '↩️ بازپرداخت', 'callback_data' => "refund:{$o['id']}"]]]);
}

/* ------------------------------------------------------------------ webhook */
function mgb_setup_webhook() {
    if (!mgb_opt('token')) return ['ok' => false, 'msg' => 'اول توکن ربات را ذخیره کنید.'];
    $o = array_merge(mgb_defaults(), get_option('mgb_settings', []));
    if (empty($o['secret'])) { $o['secret'] = wp_generate_password(32, false); update_option('mgb_settings', $o, false); }
    $r = mgb_tg('setWebhook', ['url' => rest_url('mgb/v1/hook'), 'secret_token' => $o['secret'], 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
    mgb_tg('setMyCommands', ['commands' => [['command' => 'start', 'description' => 'Menu / منو'], ['command' => 'orders', 'description' => 'My orders / سفارش‌ها']]]);
    return ['ok' => !empty($r['ok']), 'msg' => !empty($r['ok']) ? 'ربات وصل شد ✅' : ('خطا: ' . ($r['description'] ?? 'no response'))];
}
function mgb_status() {
    $me = mgb_opt('token') ? mgb_tg('getMe') : null;
    $wh = mgb_opt('token') ? mgb_tg('getWebhookInfo') : null;
    $nb = wp_remote_get('https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=usdt', ['timeout' => 8]);
    return ['version' => MGB_VER, 'bot' => $me['result']['username'] ?? null, 'webhook' => $wh['result']['url'] ?? null,
        'webhook_error' => $wh['result']['last_error_message'] ?? null, 'admins' => count(mgb_admins()), 'address_set' => (bool) mgb_opt('address'),
        'ton_usd' => mgb_ton_usd(), 'bsc_block' => ($b = mgb_rpc('eth_blockNumber', [])) ? hexdec($b) : null,
        'mrkt' => mgb_opt('mrkt_token') ? (mgb_mrkt('gifts/collections') !== null ? 'ok' : 'token rejected / endpoint changed') : 'no token',
        'nobitex_reachable' => !is_wp_error($nb) && wp_remote_retrieve_response_code($nb) === 200,
        'cron_next' => wp_next_scheduled('mgb_check_payments') ? gmdate('c', wp_next_scheduled('mgb_check_payments')) : null,
        'open_orders' => (int) $GLOBALS['wpdb']->get_var('SELECT COUNT(*) FROM ' . mgb_table() . " WHERE status IN ('awaiting','paid','request')")];
}
add_action('rest_api_init', function () {
    register_rest_route('mgb/v1', '/hook', ['methods' => 'POST', 'permission_callback' => function ($r) {
        $s = mgb_opt('secret'); return $s && hash_equals($s, (string) $r->get_header('x_telegram_bot_api_secret_token'));
    }, 'callback' => function ($r) { mgb_handle((array) $r->get_json_params()); return ['ok' => true]; }]);
    register_rest_route('mgb/v1', '/status', ['methods' => 'GET', 'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => 'mgb_status']);
});

function mgb_state($chat, $v = null) {
    $k = 'mgb_st_' . $chat;
    if ($v === null) return get_transient($k) ?: [];
    if ($v === false) { delete_transient($k); return []; }
    set_transient($k, $v, 1800); return $v;
}
function mgb_menu($chat, $lang) {
    mgb_send($chat, mgb_t($lang, 'menu'), [[['text' => mgb_t($lang, 'b_buy'), 'callback_data' => 'cols']], [['text' => mgb_t($lang, 'b_link'), 'callback_data' => 'link']],
        [['text' => mgb_t($lang, 'b_orders'), 'callback_data' => 'orders'], ['text' => mgb_t($lang, 'b_support'), 'callback_data' => 'support']]]);
}
function mgb_handle($u) {
    if (!empty($u['callback_query'])) { mgb_callback($u['callback_query']); return; }
    $m = $u['message'] ?? null;
    if (!$m || ($m['chat']['type'] ?? '') !== 'private') return;
    $chat = (int) $m['chat']['id'];
    $lang = mgb_lang($m['from']['language_code'] ?? 'fa');
    $text = trim((string) ($m['text'] ?? ''));
    $user = !empty($m['from']['username']) ? '@' . $m['from']['username'] : trim(($m['from']['first_name'] ?? '') . ' ' . ($m['from']['last_name'] ?? ''));
    if ($text === '/myid') { mgb_send($chat, "Chat ID: <code>$chat</code>"); return; }
    if (mgb_is_admin($chat) && preg_match('/^\/quote\s+(\d+)\s+([\d.]+)\s*(ton|usd|usdt)?$/i', $text, $q)) { mgb_admin_quote((int) $q[1], (float) $q[2], strtolower($q[3] ?? 'ton')); return; }
    if (mgb_is_admin($chat) && $text === '/open') { mgb_admin_open($chat); return; }
    if ($text === '/start' || $text === '/menu') { mgb_state($chat, false); mgb_menu($chat, $lang); return; }
    if ($text === '/orders') { mgb_my_orders($chat, $lang); return; }
    $st = mgb_state($chat);
    if (($st['step'] ?? '') === 'link') {
        if (!preg_match('~(?:https?://)?t\.me/nft/([A-Za-z0-9]+)-(\d+)~', $text, $mm)) { mgb_send($chat, mgb_t($lang, 'badlink')); return; }
        global $wpdb;
        $link = "https://t.me/nft/{$mm[1]}-{$mm[2]}";
        $wpdb->insert(mgb_table(), ['chat_id' => $chat, 'username' => $user, 'lang' => $lang, 'item_title' => "{$mm[1]} #{$mm[2]}", 'item_link' => $link,
            'recipient' => $user, 'status' => 'request', 'created_at' => current_time('mysql', true)]);
        $oid = (int) $wpdb->insert_id;
        mgb_state($chat, false);
        mgb_send($chat, mgb_t($lang, 'link_ok', $oid));
        mgb_notify_admins("🔗 درخواست قیمت — سفارش #$oid\n$link\nخریدار: " . esc_html($user) . "\n\nبرای قیمت‌دادن (قیمت خرید در MRKT):\n<code>/quote $oid 12.5 ton</code>\nیا <code>/quote $oid 15 usd</code>");
        return;
    }
    if (($st['step'] ?? '') === 'recipient' && !empty($st['item'])) {
        if (!preg_match('/^@?([A-Za-z][A-Za-z0-9_]{4,31})$/', $text, $mm)) { mgb_send($chat, mgb_t($lang, 'ask_rcpt'), [[['text' => mgb_t($lang, 'b_me'), 'callback_data' => 'me']]]); return; }
        mgb_state($chat, false);
        mgb_create_quote($chat, $user, $lang, $st['item'], '@' . $mm[1]);
        return;
    }
    mgb_menu($chat, $lang);
}
function mgb_callback($c) {
    $chat = (int) ($c['message']['chat']['id'] ?? 0);
    $data = (string) ($c['data'] ?? '');
    $lang = mgb_lang($c['from']['language_code'] ?? 'fa');
    $user = !empty($c['from']['username']) ? '@' . $c['from']['username'] : (string) ($c['from']['first_name'] ?? '');
    mgb_tg('answerCallbackQuery', ['callback_query_id' => $c['id']]);
    if ($data === 'cols') {
        $cols = mgb_collections();
        if (!$cols) { mgb_state($chat, ['step' => 'link']); mgb_send($chat, mgb_t($lang, 'nocat')); return; }
        $kb = []; foreach ($cols as $i => $name) $kb[] = [['text' => $name, 'callback_data' => 'col:' . $i]];
        mgb_state($chat, ['cols' => $cols]);
        mgb_send($chat, mgb_t($lang, 'cols'), $kb); return;
    }
    if (strpos($data, 'col:') === 0) {
        $st = mgb_state($chat); $name = $st['cols'][(int) substr($data, 4)] ?? null;
        if (!$name) { mgb_menu($chat, $lang); return; }
        $items = mgb_listings($name, 5);
        if (!$items) { mgb_send($chat, mgb_t($lang, 'empty')); return; }
        $kb = [];
        foreach ($items as $i => $it) { $p = mgb_price($it[3]); if ($p) $kb[] = [['text' => $it[1] . ' — ' . number_format($p[1], 2) . ' USDT', 'callback_data' => 'item:' . $i]]; }
        mgb_state($chat, ['cols' => $st['cols'], 'items' => $items]);
        mgb_send($chat, mgb_t($lang, 'list', esc_html($name)), $kb); return;
    }
    if (strpos($data, 'item:') === 0) {
        $st = mgb_state($chat); $it = $st['items'][(int) substr($data, 5)] ?? null;
        if (!$it) { mgb_menu($chat, $lang); return; }
        mgb_state($chat, ['step' => 'recipient', 'item' => $it]);
        mgb_send($chat, mgb_t($lang, 'ask_rcpt'), [[['text' => mgb_t($lang, 'b_me'), 'callback_data' => 'me']]]); return;
    }
    if ($data === 'me') {
        $st = mgb_state($chat);
        if (empty($st['item'])) { mgb_menu($chat, $lang); return; }
        if (strpos($user, '@') !== 0) { mgb_send($chat, mgb_t($lang, 'ask_rcpt')); return; } // no username: must type one
        mgb_state($chat, false);
        mgb_create_quote($chat, $user, $lang, $st['item'], $user); return;
    }
    if ($data === 'link') { mgb_state($chat, ['step' => 'link']); mgb_send($chat, mgb_t($lang, 'nocat')); return; }
    if ($data === 'orders') { mgb_my_orders($chat, $lang); return; }
    if ($data === 'support') { mgb_send($chat, mgb_t($lang, 'support', esc_html(mgb_opt('support', '@meftacard')))); return; }
    if (preg_match('/^(paid|cancel|done|refund):(\d+)$/', $data, $m)) {
        $o = mgb_order((int) $m[2]);
        if (!$o) return;
        global $wpdb;
        if (in_array($m[1], ['paid', 'cancel'], true) && (int) $o['chat_id'] !== $chat) return;
        if ($m[1] === 'paid') {
            if ($o['status'] === 'paid') { mgb_send($chat, mgb_t($lang, 'paid', $o['id'], esc_html($o['recipient']))); return; }
            if ($o['status'] !== 'awaiting') { mgb_send($chat, mgb_t($lang, 'expired', $o['id'])); return; }
            if (!mgb_check_payments((int) $o['id'])) mgb_send($chat, mgb_t($lang, 'notyet'));
            return;
        }
        if ($m[1] === 'cancel' && $o['status'] === 'awaiting') { $wpdb->update(mgb_table(), ['status' => 'cancelled'], ['id' => $o['id']]); mgb_send($chat, mgb_t($lang, 'cancelled')); return; }
        if (!mgb_is_admin($chat)) return;
        if ($m[1] === 'done' && $o['status'] === 'paid') {
            $wpdb->update(mgb_table(), ['status' => 'delivered', 'done_at' => current_time('mysql', true)], ['id' => $o['id']]);
            mgb_send($o['chat_id'], mgb_t($o['lang'], 'delivered', $o['id'])); mgb_send($chat, "✅ سفارش #{$o['id']} تحویل‌شده ثبت شد."); return;
        }
        if ($m[1] === 'refund' && in_array($o['status'], ['paid', 'request'], true)) {
            $wpdb->update(mgb_table(), ['status' => 'refunded', 'done_at' => current_time('mysql', true)], ['id' => $o['id']]);
            mgb_send($o['chat_id'], mgb_t($o['lang'], 'refunded', $o['id'], esc_html(mgb_opt('support', '@meftacard'))));
            mgb_send($chat, "↩️ سفارش #{$o['id']} بازپرداخت ثبت شد. مبلغ {$o['pay_amount']} USDT را به خریدار برگردانید." . ($o['tx_hash'] ? "\nTX پرداخت: https://bscscan.com/tx/{$o['tx_hash']}" : '')); return;
        }
    }
}
function mgb_admin_quote($oid, $amount, $unit) {
    global $wpdb;
    $o = mgb_order($oid);
    if (!$o || $o['status'] !== 'request') { mgb_notify_admins("سفارش #$oid پیدا نشد یا منتظر قیمت نیست."); return; }
    $ton = $unit === 'ton' ? $amount : ($amount / max(0.000001, mgb_ton_usd()));
    $wpdb->update(mgb_table(), ['status' => 'cancelled'], ['id' => $oid]); // replaced by a priced order
    mgb_create_quote((int) $o['chat_id'], $o['username'], $o['lang'], [$o['item_id'], $o['item_title'], $o['item_link'], $ton], $o['recipient']);
    mgb_notify_admins("قیمت سفارش #$oid برای خریدار فرستاده شد.");
}
function mgb_admin_open($chat) {
    global $wpdb;
    $rows = $wpdb->get_results('SELECT id,status,item_title,recipient,pay_amount FROM ' . mgb_table() . " WHERE status IN ('request','awaiting','paid') ORDER BY id DESC LIMIT 20", ARRAY_A);
    if (!$rows) { mgb_send($chat, 'سفارش باز نداریم.'); return; }
    $lines = array_map(function ($r) { return "#{$r['id']} · {$r['status']} · " . esc_html($r['item_title']) . " → " . esc_html($r['recipient']) . ($r['pay_amount'] > 0 ? " · {$r['pay_amount']} USDT" : ''); }, $rows);
    mgb_send($chat, "📋 سفارش‌های باز:\n" . implode("\n", $lines));
}
function mgb_my_orders($chat, $lang) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare('SELECT id,status,item_title,pay_amount FROM ' . mgb_table() . ' WHERE chat_id=%d ORDER BY id DESC LIMIT 10', $chat), ARRAY_A);
    if (!$rows) { mgb_send($chat, mgb_t($lang, 'no_orders')); return; }
    $L = ['fa' => ['request' => 'در انتظار قیمت', 'awaiting' => 'در انتظار پرداخت', 'paid' => 'پرداخت‌شده، در حال تحویل', 'delivered' => 'تحویل‌شده', 'refunded' => 'بازپرداخت', 'expired' => 'منقضی', 'cancelled' => 'لغو'],
          'ar' => ['request' => 'بانتظار السعر', 'awaiting' => 'بانتظار الدفع', 'paid' => 'مدفوع، قيد التسليم', 'delivered' => 'تم التسليم', 'refunded' => 'مسترد', 'expired' => 'منتهي', 'cancelled' => 'ملغى'],
          'en' => ['request' => 'awaiting price', 'awaiting' => 'awaiting payment', 'paid' => 'paid, delivering', 'delivered' => 'delivered', 'refunded' => 'refunded', 'expired' => 'expired', 'cancelled' => 'cancelled']][$lang];
    mgb_send($chat, implode("\n", array_map(function ($r) use ($L) { return "#{$r['id']} · " . esc_html($r['item_title']) . ' · ' . ($L[$r['status']] ?? $r['status']) . ($r['pay_amount'] > 0 ? " · {$r['pay_amount']} USDT" : ''); }, $rows)));
}
