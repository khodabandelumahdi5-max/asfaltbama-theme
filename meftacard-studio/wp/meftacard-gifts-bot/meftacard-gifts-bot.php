<?php
/**
 * Plugin Name: MEFTACARD Gifts
 * Description: Adds Telegram gift (NFT) sales to the main MEFTACARD bot. Prices come from MRKT with a fixed markup; payment and order tracking use Meftacard Direct Pay, so every order has an MC- number and is matched by the same on-chain scanner. Requires Meftacard Telegram Bot 2.3+ and Meftacard Direct Pay.
 * Version: 0.2.0
 * Author: MEFTACARD
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

const MGB_VER = '0.2.0';
const MGB_MAIL_DOMAIN = '@telegram.invalid'; // gift buyers have no email; mail to these addresses is skipped

/* ------------------------------------------------------------------ settings */
function mgb_defaults() {
    return ['markup' => '5', 'min_profit' => '1', 'fixed_fee' => '0.5', 'fx_buffer' => '1', 'collections' => '', 'mrkt_token' => ''];
}
function mgb_opt($k) {
    $o = array_merge(mgb_defaults(), (array) get_option('mgb_settings', []));
    return $o[$k];
}
function mgb_ready() { return class_exists('Mefta_TG_Bot') && class_exists('Mefta_Direct_Pay') && class_exists('Mefta_Telegram_Notifier'); }

add_action('admin_notices', function () {
    if (!mgb_ready() && current_user_can('manage_options'))
        echo '<div class="notice notice-error"><p><b>MEFTACARD Gifts</b>: برای کار کردن، افزونه‌های «Meftacard Telegram Bot» (نسخه ۲.۳ یا بالاتر) و «Meftacard Direct Pay» باید فعال باشند.</p></div>';
});
add_action('admin_menu', function () { add_options_page('MEFTACARD Gifts', 'MEFTACARD Gifts', 'manage_options', 'mgb', 'mgb_settings_page'); });
add_action('admin_init', function () {
    register_setting('mgb', 'mgb_settings', ['sanitize_callback' => function ($in) {
        $out = array_merge(mgb_defaults(), (array) get_option('mgb_settings', []));
        foreach (['markup', 'min_profit', 'fixed_fee', 'fx_buffer'] as $k) if (isset($in[$k])) $out[$k] = (string) max(0, (float) $in[$k]);
        if (isset($in['collections'])) $out['collections'] = sanitize_textarea_field($in['collections']);
        if (!empty($in['mrkt_token'])) $out['mrkt_token'] = trim(sanitize_text_field($in['mrkt_token']));
        if (!empty($in['mrkt_clear'])) $out['mrkt_token'] = '';
        return $out;
    }]);
});
function mgb_settings_page() {
    if (!current_user_can('manage_options')) return;
    $o = array_merge(mgb_defaults(), (array) get_option('mgb_settings', []));
    echo '<div class="wrap" dir="rtl"><h1>MEFTACARD Gifts — فروش گیفت تلگرام در ربات اصلی</h1><form method="post" action="options.php">';
    settings_fields('mgb');
    $row = function ($k, $label, $help, $textarea = false) use ($o) {
        echo '<tr><th><label for="mgb_' . esc_attr($k) . '">' . esc_html($label) . '</label></th><td>';
        if ($textarea) echo '<textarea id="mgb_' . esc_attr($k) . '" name="mgb_settings[' . esc_attr($k) . ']" rows="4" class="large-text" dir="ltr">' . esc_textarea($o[$k]) . '</textarea>';
        elseif ($k === 'mrkt_token') echo '<input id="mgb_mrkt_token" type="password" name="mgb_settings[mrkt_token]" class="regular-text" autocomplete="off" placeholder="' . esc_attr($o['mrkt_token'] ? 'ذخیره شده — برای تغییر مقدار جدید وارد کنید' : '') . '">' . ($o['mrkt_token'] ? ' <label><input type="checkbox" name="mgb_settings[mrkt_clear]" value="1"> حذف</label>' : '');
        else echo '<input id="mgb_' . esc_attr($k) . '" type="text" name="mgb_settings[' . esc_attr($k) . ']" value="' . esc_attr($o[$k]) . '" class="small-text" dir="ltr">';
        echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
    };
    echo '<table class="form-table">';
    $row('markup', 'درصد سود', 'پیش‌فرض ۵');
    $row('min_profit', 'حداقل سود هر سفارش (دلار)', 'برای گیفت‌های ارزان که ۵٪ آن‌ها کمتر از هزینه‌هاست؛ پیش‌فرض ۱');
    $row('fixed_fee', 'هزینه ثابت هر سفارش (دلار)', 'کارمزد انتقال گیفت و شبکه؛ پیش‌فرض ۰٫۵');
    $row('fx_buffer', 'حاشیه نوسان TON (درصد)', 'برای پوشش تغییر قیمت TON تا زمان خرید؛ پیش‌فرض ۱');
    $row('collections', 'کالکشن‌ها (هر کدام یک خط)', 'نام کالکشن‌ها دقیقاً مثل MRKT (مثلاً Plush Pepe). خالی = از MRKT.', true);
    $row('mrkt_token', 'توکن MRKT', 'برای نمایش خودکار قیمت‌ها. بدون آن، مشتری لینک گیفت را می‌فرستد و شما با /quote قیمت می‌دهید.');
    echo '</table>';
    submit_button('ذخیره');
    echo '</form><h2>وضعیت</h2><pre dir="ltr" style="text-align:left;background:#fff;padding:12px">' . esc_html(wp_json_encode(mgb_status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    echo '<p>مدیر در ربات: <code>/quote شماره‌درخواست قیمت ton</code> (مثلاً <code>/quote 3 25 ton</code> یا <code>/quote 3 30 usd</code>) و <code>/requests</code> برای درخواست‌های منتظر قیمت.</p></div>';
}

/* ------------------------------------------------------------------ helpers */
function mgb_api($method, $params) { return mgb_ready() ? Mefta_TG_Bot::api($method, $params) : null; }
function mgb_send($chat, $text, $kb = null) {
    $p = ['chat_id' => $chat, 'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]];
    if ($kb) $p['reply_markup'] = ['inline_keyboard' => $kb];
    return mgb_api('sendMessage', $p);
}
function mgb_admin_chat() { return mgb_ready() ? (string) Mefta_Telegram_Notifier::settings()['chat_id'] : ''; }
function mgb_notify_admin($text, $kb = null) { $a = mgb_admin_chat(); if ($a !== '') mgb_send($a, $text, $kb); }
function mgb_state($chat, $v = null) {
    $k = 'mgb_st_' . md5((string) $chat);
    if ($v === null) return (array) (get_transient($k) ?: []);
    if ($v === false) { delete_transient($k); return []; }
    set_transient($k, $v, 30 * MINUTE_IN_SECONDS); return $v;
}
function mgb_rpc($method, $params) {
    foreach (['https://bsc-rpc.publicnode.com', 'https://bsc-dataseed.binance.org'] as $url) {
        $r = wp_remote_post($url, ['timeout' => 12, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])]);
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
    if ($p <= 0) {
        $r = wp_remote_get('https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=usdt', ['timeout' => 10]);
        if (!is_wp_error($r)) { $j = json_decode(wp_remote_retrieve_body($r), true); $p = (float) ($j['stats']['ton-usdt']['latest'] ?? 0); }
    }
    if ($p > 0) set_transient('mgb_ton_usd', $p, 120);
    return $p;
}
/** [cost_usd, price_usd] for a cost in TON, or null. Price = max(cost × (1+markup), cost + min profit). */
function mgb_price($ton) {
    $rate = mgb_ton_usd();
    if ($rate <= 0 || $ton <= 0) return null;
    $cost = $ton * $rate * (1 + (float) mgb_opt('fx_buffer') / 100) + (float) mgb_opt('fixed_fee');
    $price = max($cost * (1 + (float) mgb_opt('markup') / 100), $cost + (float) mgb_opt('min_profit'));
    return [round($cost, 2), ceil($price * 100) / 100];
}

/* ------------------------------------------------------------------ MRKT (read-only) */
function mgb_mrkt($path, $body = null) {
    $tok = mgb_opt('mrkt_token');
    if (!$tok) return null;
    $a = ['timeout' => 15, 'headers' => ['Authorization' => $tok, 'Content-Type' => 'application/json', 'Accept' => 'application/json']];
    $r = $body === null ? wp_remote_get('https://api.tgmrkt.io/api/v1/' . $path, $a) : wp_remote_post('https://api.tgmrkt.io/api/v1/' . $path, $a + ['body' => wp_json_encode($body)]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) return null;
    return json_decode(wp_remote_retrieve_body($r), true);
}
function mgb_nano($v) { return is_numeric($v) ? ((float) $v > 100000 ? (float) $v / 1e9 : (float) $v) : 0; }
function mgb_collections() {
    $fixed = array_values(array_filter(array_map('trim', explode("\n", (string) mgb_opt('collections')))));
    if ($fixed) return $fixed;
    if ($c = get_transient('mgb_cols')) return $c;
    $out = [];
    foreach ((array) mgb_mrkt('gifts/collections') as $row) if (is_array($row) && ($row['title'] ?? $row['name'] ?? '')) $out[] = $row['title'] ?? $row['name'];
    $out = array_slice($out, 0, 12);
    if ($out) set_transient('mgb_cols', $out, 600);
    return $out;
}
/** Cheapest listings: [[id, title, link, price_ton], ...] */
function mgb_listings($collection, $n = 5) {
    $key = 'mgb_l_' . md5($collection);
    if ($c = get_transient($key)) return $c;
    $j = mgb_mrkt('gifts/saling', ['collectionNames' => [$collection], 'modelNames' => [], 'backdropNames' => [], 'symbolNames' => [], 'ordering' => 'Price', 'lowToHigh' => true, 'count' => $n, 'cursor' => '', 'query' => null, 'promotedFirst' => false]);
    $out = [];
    foreach ((array) ($j['gifts'] ?? []) as $g) {
        $price = mgb_nano($g['salePrice'] ?? $g['price'] ?? 0);
        if ($price <= 0) continue;
        $num = $g['number'] ?? '';
        $model = $g['modelTitle'] ?? $g['modelName'] ?? '';
        $slug = preg_replace('/[^A-Za-z0-9]/', '', (string) ($g['name'] ?? $collection));
        $out[] = [(string) ($g['id'] ?? ''), trim($collection . ($num !== '' ? " #$num" : '') . ($model ? " · $model" : '')), $slug && $num !== '' ? "https://t.me/nft/{$slug}-{$num}" : '', $price];
    }
    if ($out) set_transient($key, $out, 90);
    return $out;
}

/* ------------------------------------------------------------------ texts */
function mgb_t($lang, $k, ...$a) {
    static $T = [
        'fa' => [
            'btn' => '🎁 گیفت تلگرام (NFT)', 'cols' => "🎁 <b>گیفت‌های تلگرام</b>\nیک کالکشن انتخاب کنید (قیمت نهایی با تتر):",
            'nocat' => "لینک گیفتی را که می‌خواهید بفرستید (مثلاً <code>t.me/nft/PlushPepe-123</code>) تا قیمتش را همین‌جا بفرستیم.",
            'b_link' => '🔗 گیفت مشخصی می‌خواهم', 'list' => 'ارزان‌ترین گیفت‌های <b>%s</b>:', 'empty' => 'الان گیفتی در این کالکشن پیدا نشد. کالکشن دیگری انتخاب کنید.',
            'ask' => "گیفت به کدام حساب تلگرام فرستاده شود؟\nیوزرنیم را بفرستید (مثلاً <code>@username</code>) یا «خودم» را بزنید.", 'me' => '👤 خودم',
            'inv' => "🧾 <b>سفارش</b> <code>%s</code>\n🎁 %s\n👤 گیرنده: %s\n\n💵 <b>مبلغ: %s USDT</b>\n🔗 شبکه: <b>BEP20 (BSC)</b>\n📬 آدرس:\n<code>%s</code>\n\n⚠️ دقیقاً همین مبلغ را بفرستید؛ رقم‌های اعشار پرداخت شما را شناسایی می‌کنند. اگر از صرافی برداشت می‌کنید، کارمزد را اضافه کنید.\n⏳ این فاکتور %d دقیقه معتبر است. پرداخت خودکار تأیید می‌شود و همین‌جا خبرتان می‌کنیم.",
            'paid_btn' => '✅ پرداخت کردم', 'checking' => 'در حال بررسی شبکه… اگر پرداخت دیده شود، چند لحظه دیگر همین‌جا پیام تأیید می‌گیرید. معمولاً ۱ تا ۳ دقیقه طول می‌کشد.',
            'req_ok' => "✅ درخواست شما ثبت شد (شماره %d). قیمت را تا چند دقیقه دیگر همین‌جا می‌فرستیم.", 'badlink' => 'لینک معتبر نیست. باید شبیه <code>t.me/nft/Name-123</code> باشد.',
            'unavail' => 'فروش گیفت موقتاً در دسترس نیست؛ کمی بعد دوباره امتحان کنید.',
            'dlv' => '🎉 گیفت سفارش <code>%s</code> برای %s فرستاده شد. از خریدتان ممنونیم!',
        ],
        'ar' => [
            'btn' => '🎁 هدايا تيليغرام (NFT)', 'cols' => "🎁 <b>هدايا تيليغرام</b>\nاختر مجموعة (السعر النهائي بالتيثر):",
            'nocat' => "أرسل رابط الهدية التي تريدها (مثل <code>t.me/nft/PlushPepe-123</code>) لنرسل لك السعر هنا.",
            'b_link' => '🔗 أريد هدية محددة', 'list' => 'أرخص هدايا <b>%s</b>:', 'empty' => 'لا توجد هدايا في هذه المجموعة الآن. اختر مجموعة أخرى.',
            'ask' => "إلى أي حساب تيليغرام نرسل الهدية؟\nأرسل اسم المستخدم (مثل <code>@username</code>) أو اضغط «حسابي».", 'me' => '👤 حسابي',
            'inv' => "🧾 <b>الطلب</b> <code>%s</code>\n🎁 %s\n👤 المستلم: %s\n\n💵 <b>المبلغ: %s USDT</b>\n🔗 الشبكة: <b>BEP20 (BSC)</b>\n📬 العنوان:\n<code>%s</code>\n\n⚠️ أرسل هذا المبلغ بالضبط؛ الأرقام العشرية تحدد دفعتك. إذا سحبت من منصة تداول، أضف رسوم السحب.\n⏳ هذه الفاتورة صالحة لمدة %d دقيقة. يتم تأكيد الدفع تلقائياً وسنخبرك هنا.",
            'paid_btn' => '✅ دفعت', 'checking' => 'جارٍ فحص الشبكة… إذا ظهر الدفع ستصلك رسالة التأكيد هنا خلال لحظات. عادةً 1 إلى 3 دقائق.',
            'req_ok' => "✅ تم تسجيل طلبك (رقم %d). سنرسل لك السعر هنا خلال دقائق.", 'badlink' => 'الرابط غير صالح. يجب أن يكون مثل <code>t.me/nft/Name-123</code>.',
            'unavail' => 'بيع الهدايا غير متاح مؤقتاً؛ حاول بعد قليل.',
            'dlv' => '🎉 تم إرسال هدية الطلب <code>%s</code> إلى %s. شكراً لشرائك!',
        ],
        'en' => [
            'btn' => '🎁 Telegram gifts (NFT)', 'cols' => "🎁 <b>Telegram gifts</b>\nChoose a collection (final price in USDT):",
            'nocat' => "Send the link of the gift you want (e.g. <code>t.me/nft/PlushPepe-123</code>) and we'll send the price here.",
            'b_link' => '🔗 I want a specific gift', 'list' => 'Cheapest <b>%s</b> gifts:', 'empty' => 'No gifts in this collection right now. Choose another one.',
            'ask' => "Which Telegram account should receive the gift?\nSend a username (e.g. <code>@username</code>) or tap “Me”.", 'me' => '👤 Me',
            'inv' => "🧾 <b>Order</b> <code>%s</code>\n🎁 %s\n👤 Recipient: %s\n\n💵 <b>Amount: %s USDT</b>\n🔗 Network: <b>BEP20 (BSC)</b>\n📬 Address:\n<code>%s</code>\n\n⚠️ Send exactly this amount; the decimals identify your payment. If you withdraw from an exchange, add the fee.\n⏳ This invoice is valid for %d minutes. Payment is confirmed automatically and we'll tell you here.",
            'paid_btn' => '✅ I have paid', 'checking' => "Checking the network… if the payment is there you'll get a confirmation here in a moment. It usually takes 1–3 minutes.",
            'req_ok' => "✅ Request received (no. %d). We'll send the price here within minutes.", 'badlink' => 'That link is not valid. It should look like <code>t.me/nft/Name-123</code>.',
            'unavail' => 'Gift sales are temporarily unavailable; please try again shortly.',
            'dlv' => '🎉 The gift for order <code>%s</code> was sent to %s. Thank you!',
        ],
    ];
    $s = $T[$lang][$k] ?? $T['en'][$k] ?? $k;
    return $a ? vsprintf($s, $a) : $s;
}
function mgb_is_button($text) { foreach (['fa', 'ar', 'en'] as $l) if ($text === mgb_t($l, 'btn')) return true; return false; }

/* ------------------------------------------------------------------ orders (Direct Pay) */
function mgb_pending_units() {
    $ids = get_posts(['post_type' => 'mefta_pay_order', 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_query' => [['key' => '_dp_status', 'value' => 'pending']]]);
    $out = [];
    foreach ($ids as $id) $out[(int) get_post_meta($id, '_dp_units', true)] = $id;
    return $out;
}
function mgb_new_order_id() {
    $alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $oid = 'MC-';
        for ($i = 0; $i < 8; $i++) $oid .= $alpha[wp_rand(0, strlen($alpha) - 1)];
        $dupe = get_posts(['post_type' => 'mefta_pay_order', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => [['key' => '_dp_order_id', 'value' => $oid]]]);
    } while ($dupe);
    return $oid;
}
/** Creates a Direct Pay invoice for a gift and sends it to the customer. $item = [id, title, link, price_ton]. */
function mgb_create_invoice($chat, $lang, $buyer, $item, $recipient) {
    $dp = Mefta_Direct_Pay::settings();
    $p = mgb_price((float) $item[3]);
    if (!$p || empty($dp['wallet'])) { mgb_send($chat, mgb_t($lang, 'unavail')); return 0; }
    $base = (int) round($p[1] * 100) * 100;
    $taken = mgb_pending_units();
    $units = 0;
    for ($i = 0; $i < 200; $i++) { $try = $base + wp_rand(11, 999); if (!isset($taken[$try])) { $units = $try; break; } }
    if (!$units) { mgb_send($chat, mgb_t($lang, 'unavail')); return 0; }
    $oid = mgb_new_order_id();
    $pid = wp_insert_post(['post_type' => 'mefta_pay_order', 'post_status' => 'publish', 'post_title' => $oid], true);
    if (is_wp_error($pid)) { mgb_send($chat, mgb_t($lang, 'unavail')); return 0; }
    $minutes = max(10, (int) ($dp['minutes'] ?? 30));
    $meta = ['order_id' => $oid, 'email' => 'tg-' . $chat . MGB_MAIL_DOMAIN, 'product' => '🎁 Gift: ' . $item[1] . ' → ' . $recipient,
        'base_amount' => number_format($p[1], 2, '.', ''), 'pay_amount' => number_format($units / 10000, 4, '.', ''), 'units' => $units,
        'wallet' => $dp['wallet'], 'status' => 'pending', 'expires' => time() + 60 * $minutes, 'tg_chat' => (string) $chat, 'tg_lang' => $lang,
        'gift' => '1', 'gift_recipient' => $recipient, 'gift_link' => $item[2], 'gift_item' => $item[0], 'gift_ton' => (string) $item[3], 'gift_cost' => (string) $p[0], 'gift_buyer' => $buyer];
    foreach ($meta as $k => $v) update_post_meta($pid, '_dp_' . $k, $v);
    if (!get_option('mefta_dp_cursor') && ($bn = mgb_rpc('eth_blockNumber', []))) update_option('mefta_dp_cursor', max(0, hexdec($bn) - 50), false);
    mgb_send($chat, mgb_t($lang, 'inv', $oid, esc_html($item[1]), esc_html($recipient), $meta['pay_amount'], $dp['wallet'], $minutes),
        [[['text' => mgb_t($lang, 'paid_btn'), 'callback_data' => 'g:chk:' . $pid]]]);
    mgb_notify_admin("🧾 <b>فاکتور گیفت</b> <code>$oid</code>\n🎁 " . esc_html($item[1]) . ($item[2] ? "\n" . esc_html($item[2]) : '') . "\n👤 گیرنده: " . esc_html($recipient)
        . "\n💵 {$meta['pay_amount']} USDT · هزینه تخمینی {$p[0]}$ ({$item[3]} TON) · سود تخمینی " . number_format($p[1] - $p[0], 2) . '$');
    return $pid;
}

/* ------------------------------------------------------------------ manual price requests */
function mgb_requests($v = null) {
    if ($v === null) return (array) get_option('mgb_requests', []);
    update_option('mgb_requests', array_slice($v, -200, null, true), false); return $v;
}
function mgb_admin_quote($rid, $amount, $unit) {
    $all = mgb_requests();
    $r = $all[$rid] ?? null;
    if (!$r) { mgb_notify_admin("درخواست $rid پیدا نشد (/requests)."); return; }
    $ton = $unit === 'ton' ? $amount : $amount / max(0.000001, mgb_ton_usd());
    unset($all[$rid]); mgb_requests($all);
    $pid = mgb_create_invoice($r['chat'], $r['lang'], $r['buyer'], ['', $r['title'], $r['link'], $ton], $r['recipient']);
    mgb_notify_admin($pid ? "✅ قیمت درخواست $rid برای مشتری فرستاده شد." : "❌ فاکتور درخواست $rid ساخته نشد (قیمت TON یا کیف پول در دسترس نیست).");
}

/* ------------------------------------------------------------------ bot hooks */
add_filter('mefta_tg_menu_rows', function ($rows, $lang) {
    if (!mgb_ready()) return $rows;
    array_unshift($rows, [['text' => mgb_t($lang, 'btn')]]);
    return $rows;
}, 10, 2);

/** The main bot keeps its own step (e.g. waiting for an order ID); entering the gift flow ends it, like any other menu button does. */
function mgb_clear_bot_state($chat) { delete_transient('mefta_tg_state_' . md5((string) $chat)); }
function mgb_show_collections($chat, $lang) {
    $cols = mgb_collections();
    if (!$cols) { mgb_state($chat, ['step' => 'link']); mgb_send($chat, mgb_t($lang, 'nocat')); return; }
    $kb = [];
    foreach ($cols as $i => $c) $kb[] = [['text' => $c, 'callback_data' => 'g:col:' . $i]];
    $kb[] = [['text' => mgb_t($lang, 'b_link'), 'callback_data' => 'g:link']];
    mgb_state($chat, ['cols' => $cols]);
    mgb_send($chat, mgb_t($lang, 'cols'), $kb);
}
/** True for menu buttons (emoji + words), commands and order IDs; false for a username or a gift link. */
function mgb_looks_like_other_action($text) {
    if ($text === '' || $text[0] === '/') return $text !== '';
    if (preg_match('/\bMC-[A-Z0-9]{8}\b/i', $text)) return true;
    if (preg_match('~t\.me/nft/~i', $text) || preg_match('/^@?[A-Za-z][A-Za-z0-9_]{4,31}$/', $text)) return false;
    return (bool) preg_match('/^[^\x00-\x7F]/u', $text) && mb_strpos($text, ' ') !== false; // e.g. "🔎 پیگیری سفارش"
}
function mgb_buyer($from) { return !empty($from['username']) ? '@' . $from['username'] : trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')); }

add_filter('mefta_tg_handle_message', function ($handled, $m, $lang, $admin) {
    if ($handled || !mgb_ready() || ($m['chat']['type'] ?? '') !== 'private') return $handled;
    $chat = $m['chat']['id'];
    $text = trim((string) ($m['text'] ?? ''));
    if ($admin && preg_match('/^\/quote\s+(\d+)\s+([\d.]+)\s*(ton|usd|usdt)?$/i', $text, $q)) { mgb_admin_quote((int) $q[1], (float) $q[2], strtolower($q[3] ?? 'ton')); return true; }
    if ($admin && $text === '/requests') {
        $rs = mgb_requests();
        mgb_send($chat, $rs ? "🔗 درخواست‌های منتظر قیمت:\n" . implode("\n", array_map(function ($id, $r) { return "$id · " . esc_html($r['link']) . ' → ' . esc_html($r['recipient']); }, array_keys($rs), $rs)) . "\n\n<code>/quote شماره قیمت ton</code>" : 'درخواست منتظری نیست.');
        return true;
    }
    if (mgb_is_button($text) || $text === '/gifts') { mgb_clear_bot_state($chat); mgb_show_collections($chat, $lang); return true; }
    $st = mgb_state($chat);
    // A menu button or command while in the gift flow means the customer moved on: leave the flow, let the bot handle it.
    if (!empty($st['step']) && mgb_looks_like_other_action($text)) { mgb_state($chat, false); return false; }
    if (($st['step'] ?? '') === 'link') {
        if (!preg_match('~(?:https?://)?t\.me/nft/([A-Za-z0-9]+)-(\d+)~', $text, $mm)) { mgb_send($chat, mgb_t($lang, 'badlink')); return true; }
        $all = mgb_requests();
        $rid = max((int) get_option('mgb_req_seq', 0), $all ? max(array_keys($all)) : 0) + 1;
        update_option('mgb_req_seq', $rid, false);
        $buyer = mgb_buyer($m['from'] ?? []);
        $link = "https://t.me/nft/{$mm[1]}-{$mm[2]}";
        $all[$rid] = ['chat' => $chat, 'lang' => $lang, 'buyer' => $buyer, 'title' => "{$mm[1]} #{$mm[2]}", 'link' => $link, 'recipient' => strpos($buyer, '@') === 0 ? $buyer : '—', 't' => time()];
        mgb_requests($all);
        mgb_state($chat, false);
        mgb_send($chat, mgb_t($lang, 'req_ok', $rid));
        mgb_notify_admin("🔗 <b>درخواست قیمت گیفت</b> (شماره $rid)\n$link\n👤 " . esc_html($buyer) . "\n\nقیمت خرید در MRKT را بدهید:\n<code>/quote $rid 25 ton</code>  یا  <code>/quote $rid 30 usd</code>");
        return true;
    }
    if (($st['step'] ?? '') === 'rcpt' && !empty($st['item'])) {
        if (!preg_match('/^@?([A-Za-z][A-Za-z0-9_]{4,31})$/', $text, $mm)) { mgb_send($chat, mgb_t($lang, 'ask'), [[['text' => mgb_t($lang, 'me'), 'callback_data' => 'g:me']]]); return true; }
        mgb_state($chat, false);
        mgb_create_invoice($chat, $lang, mgb_buyer($m['from'] ?? []), $st['item'], '@' . $mm[1]);
        return true;
    }
    return false;
}, 10, 4);

add_filter('mefta_tg_handle_callback', function ($handled, $cb, $lang, $admin) {
    $data = (string) ($cb['data'] ?? '');
    if ($handled || strpos($data, 'g:') !== 0 || !mgb_ready()) return $handled;
    $chat = $cb['message']['chat']['id'] ?? 0;
    mgb_api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
    $st = mgb_state($chat);
    if ($data === 'g:link') { mgb_state($chat, ['step' => 'link']); mgb_send($chat, mgb_t($lang, 'nocat')); return true; }
    if (preg_match('/^g:col:(\d+)$/', $data, $mm)) {
        $name = $st['cols'][(int) $mm[1]] ?? null;
        if (!$name) { mgb_show_collections($chat, $lang); return true; }
        $items = mgb_listings($name, 5);
        if (!$items) { mgb_send($chat, mgb_t($lang, 'empty')); return true; }
        $kb = [];
        foreach ($items as $i => $it) if ($p = mgb_price($it[3])) $kb[] = [['text' => $it[1] . ' — ' . number_format($p[1], 2) . ' USDT', 'callback_data' => 'g:it:' . $i]];
        if (!$kb) { mgb_send($chat, mgb_t($lang, 'unavail')); return true; }
        mgb_state($chat, ['cols' => $st['cols'], 'items' => $items]);
        mgb_send($chat, mgb_t($lang, 'list', esc_html($name)), $kb);
        return true;
    }
    if (preg_match('/^g:it:(\d+)$/', $data, $mm)) {
        $it = $st['items'][(int) $mm[1]] ?? null;
        if (!$it) { mgb_show_collections($chat, $lang); return true; }
        mgb_state($chat, ['step' => 'rcpt', 'item' => $it]);
        mgb_send($chat, mgb_t($lang, 'ask'), [[['text' => mgb_t($lang, 'me'), 'callback_data' => 'g:me']]]);
        return true;
    }
    if ($data === 'g:me') {
        $buyer = mgb_buyer($cb['from'] ?? []);
        if (empty($st['item']) || strpos($buyer, '@') !== 0) { mgb_send($chat, mgb_t($lang, 'ask')); return true; } // no username → type one
        mgb_state($chat, false);
        mgb_create_invoice($chat, $lang, $buyer, $st['item'], $buyer);
        return true;
    }
    if (preg_match('/^g:chk:(\d+)$/', $data, $mm)) {
        $pid = (int) $mm[1];
        if ((string) get_post_meta($pid, '_dp_tg_chat', true) !== (string) $chat) return true;
        mgb_send($chat, mgb_t($lang, 'checking'));
        if (get_post_meta($pid, '_dp_status', true) === 'pending' && !get_transient('mgb_scan_cd')) { set_transient('mgb_scan_cd', 1, 20); Mefta_Direct_Pay::scan(); }
        return true;
    }
    return true;
}, 10, 4);

/* Gift-specific wording for the bot's existing status notifications and admin card. */
add_filter('mefta_tg_customer_text', function ($text, $id, $key, $lang) {
    if (get_post_meta($id, '_dp_gift', true) !== '1' || $key !== 'n_dlv') return $text;
    return mgb_t($lang, 'dlv', esc_html(get_post_meta($id, '_dp_order_id', true)), esc_html(get_post_meta($id, '_dp_gift_recipient', true)));
}, 10, 4);
add_filter('mefta_tg_admin_card_text', function ($txt, $id) {
    if (get_post_meta($id, '_dp_gift', true) !== '1') return $txt;
    $link = get_post_meta($id, '_dp_gift_link', true);
    $paid = (float) (get_post_meta($id, '_dp_paid_amount', true) ?: get_post_meta($id, '_dp_pay_amount', true));
    $cost = (float) get_post_meta($id, '_dp_gift_cost', true);
    $txt = preg_replace('/\n📧 [^\n]*/u', '', $txt); // placeholder address is meaningless to the admin
    return $txt . "\n🎁 گیرنده: <b>" . esc_html(get_post_meta($id, '_dp_gift_recipient', true)) . '</b> · خریدار: ' . esc_html(get_post_meta($id, '_dp_gift_buyer', true))
        . ($link ? "\n🔗 " . esc_html($link) : '') . "\n💹 هزینه تخمینی {$cost}$ (" . esc_html(get_post_meta($id, '_dp_gift_ton', true)) . ' TON) · سود تخمینی ' . number_format($paid - $cost, 2) . '$'
        . "\nℹ️ گیفت را از MRKT بخرید و بفرستید، بعد «📤 ارسال محصول» را بزنید و یک کلمه (مثلاً «ارسال شد») بنویسید.";
}, 10, 2);
/* Gift buyers have no email: skip mail to the placeholder address instead of failing (and alerting). */
add_filter('pre_wp_mail', function ($return, $atts) {
    $to = is_array($atts['to'] ?? null) ? implode(',', $atts['to']) : (string) ($atts['to'] ?? '');
    return ($to !== '' && substr(strtolower(trim($to)), -strlen(MGB_MAIL_DOMAIN)) === MGB_MAIL_DOMAIN) ? true : $return;
}, 10, 2);

/* ------------------------------------------------------------------ status */
function mgb_status() {
    $nb = wp_remote_get('https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=usdt', ['timeout' => 8]);
    return ['version' => MGB_VER, 'bot_plugin' => class_exists('Mefta_TG_Bot'), 'pay_plugin' => class_exists('Mefta_Direct_Pay'),
        'hooks_ok' => mgb_ready() && has_filter('mefta_tg_menu_rows') !== false, 'wallet_set' => mgb_ready() && !empty(Mefta_Direct_Pay::settings()['wallet']),
        'admin_chat_set' => mgb_admin_chat() !== '', 'ton_usd' => mgb_ton_usd(),
        'mrkt' => mgb_opt('mrkt_token') ? (mgb_mrkt('gifts/collections') !== null ? 'ok' : 'token rejected / endpoint changed') : 'no token (manual quotes)',
        'collections' => count(mgb_collections()), 'pending_requests' => count(mgb_requests()),
        'nobitex_reachable' => !is_wp_error($nb) && wp_remote_retrieve_response_code($nb) === 200];
}
add_action('rest_api_init', function () {
    register_rest_route('mgb/v1', '/status', ['methods' => 'GET', 'permission_callback' => function () { return current_user_can('manage_options'); }, 'callback' => 'mgb_status']);
});

/* Version 0.1.x set its own cron; make sure it is not left behind. */
add_action('admin_init', function () {
    if (get_option('mgb_cleanup_02')) return;
    wp_clear_scheduled_hook('mgb_check_payments');
    update_option('mgb_cleanup_02', 1, false);
});
