<?php
/**
 * Meftacard Telegram bot: customer menu, order tracking, support relay,
 * and admin order management (deliver / reject / list / stats).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mefta_TG_Bot {

	const SECRET_OPT = 'mefta_tg_webhook_secret';
	const ORDER_CPT  = 'mefta_pay_order';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_mefta_tg_webhook', array( __CLASS__, 'handle_activate' ) );
		add_action( 'admin_post_mefta_tg_webhook_off', array( __CLASS__, 'handle_deactivate' ) );
	}

	/* ================================================================ texts */

	private static function t( $lang, $key ) {
		$fa = array(
			'welcome'   => "سلام 👋 به <b>MEFTACARD</b> خوش آمدید.\n\nخرید اشتراک هوش مصنوعی، کلید API، کارت مجازی و شماره مجازی با <b>تتر (USDT)</b>.\n\nاز منوی پایین انتخاب کنید 👇",
			'products'  => '🛒 محصولات و قیمت‌ها',
			'track'     => '🔎 پیگیری سفارش',
			'howto'     => '💳 راهنمای پرداخت',
			'support'   => '💬 پشتیبانی',
			'site'      => '🌐 سایت',
			'ask_order' => "شماره سفارش را بفرستید (مثلاً <code>MC-AB12CD34</code>).\nشماره در صفحه پرداخت و ایمیل تأیید آمده است.",
			'not_found' => '❌ سفارشی با این شماره پیدا نشد. شماره را دوباره بررسی کنید.',
			'ask_sup'   => "پیامتان را بنویسید (متن، عکس یا فایل). تیم پشتیبانی همین‌جا جواب می‌دهد.\nبرای سرعت بیشتر، شماره سفارش را هم بنویسید.",
			'sup_sent'  => '✅ پیام شما به پشتیبانی رسید. به‌زودی همین‌جا پاسخ می‌گیرید.',
			'sup_reply' => '💬 <b>پاسخ پشتیبانی:</b>',
			'buy'       => '🛍 خرید در سایت',
			'video'     => '🎬 ویدیوی آموزشی',
			'howto_txt' => "<b>راهنمای خرید با تتر</b>\n\n1️⃣ محصول را در سایت انتخاب کنید و «Buy with USDT» را بزنید.\n2️⃣ ایمیل خود را وارد و با کد ۶ رقمی تأیید کنید.\n3️⃣ <b>دقیقاً</b> مبلغ نمایش‌داده‌شده را روی شبکه <b>BEP20 (BSC)</b> به آدرس نمایش‌داده‌شده بفرستید.\n4️⃣ پرداخت خودکار تأیید و سفارش به ایمیل شما ارسال می‌شود.\n\n⚠️ فقط شبکه BEP20. اگر از صرافی برداشت می‌کنید، کارمزد را اضافه کنید تا مبلغ دقیق برسد.",
			'st_pending'   => '⏳ در انتظار پرداخت',
			'st_paid'      => '✅ پرداخت شد — در حال آماده‌سازی',
			'st_delivered' => '📦 تحویل شد',
			'st_expired'   => '⌛ منقضی شد',
			'st_rejected'  => '❌ لغو شد',
			'order'     => 'سفارش',
			'product'   => 'محصول',
			'amount'    => 'مبلغ',
			'status'    => 'وضعیت',
			'unknown'   => 'از منوی پایین انتخاب کنید 👇',
		);
		$en = array(
			'welcome'   => "Hi 👋 Welcome to <b>MEFTACARD</b>.\n\nAI subscriptions, API credits, virtual cards and virtual numbers — paid with <b>USDT</b>.\n\nChoose from the menu below 👇",
			'products'  => '🛒 Products & prices',
			'track'     => '🔎 Track order',
			'howto'     => '💳 How to pay',
			'support'   => '💬 Support',
			'site'      => '🌐 Website',
			'ask_order' => "Send your order ID (e.g. <code>MC-AB12CD34</code>).\nYou can find it on the payment screen and in your confirmation email.",
			'not_found' => '❌ No order found with this ID. Please check it and try again.',
			'ask_sup'   => "Type your message (text, photo or file). Our team will reply right here.\nInclude your order ID for faster help.",
			'sup_sent'  => '✅ Your message was sent to support. You will get a reply here soon.',
			'sup_reply' => '💬 <b>Support reply:</b>',
			'buy'       => '🛍 Buy on website',
			'video'     => '🎬 Video tutorial',
			'howto_txt' => "<b>How to buy with USDT</b>\n\n1️⃣ Choose a product on the website and tap “Buy with USDT”.\n2️⃣ Enter your email and verify it with the 6-digit code.\n3️⃣ Send the <b>exact</b> amount shown on the <b>BEP20 (BSC)</b> network to the address shown.\n4️⃣ Payment is confirmed automatically and your order is sent to your email.\n\n⚠️ BEP20 only. If you withdraw from an exchange, add the fee so the exact amount arrives.",
			'st_pending'   => '⏳ Waiting for payment',
			'st_paid'      => '✅ Paid — being prepared',
			'st_delivered' => '📦 Delivered',
			'st_expired'   => '⌛ Expired',
			'st_rejected'  => '❌ Cancelled',
			'order'     => 'Order',
			'product'   => 'Product',
			'amount'    => 'Amount',
			'status'    => 'Status',
			'unknown'   => 'Please choose from the menu below 👇',
		);
		$ar = array(
			'welcome'   => "مرحباً 👋 أهلاً بك في <b>MEFTACARD</b>.\n\nاشتراكات الذكاء الاصطناعي، مفاتيح API، بطاقات افتراضية وأرقام افتراضية — الدفع بعملة <b>USDT</b>.\n\nاختر من القائمة أدناه 👇",
			'products'  => '🛒 المنتجات والأسعار',
			'track'     => '🔎 تتبع الطلب',
			'howto'     => '💳 طريقة الدفع',
			'support'   => '💬 الدعم',
			'site'      => '🌐 الموقع',
			'ask_order' => "أرسل رقم طلبك (مثال: <code>MC-AB12CD34</code>).\nتجده في صفحة الدفع وفي رسالة التأكيد على بريدك.",
			'not_found' => '❌ لم يتم العثور على طلب بهذا الرقم. تحقق منه وحاول مرة أخرى.',
			'ask_sup'   => "اكتب رسالتك (نص أو صورة أو ملف). سيرد فريق الدعم هنا.\nأضف رقم الطلب لخدمة أسرع.",
			'sup_sent'  => '✅ تم إرسال رسالتك إلى الدعم. ستصلك الإجابة هنا قريباً.',
			'sup_reply' => '💬 <b>رد الدعم:</b>',
			'buy'       => '🛍 الشراء من الموقع',
			'video'     => '🎬 فيديو تعليمي',
			'howto_txt' => "<b>طريقة الشراء بعملة USDT</b>\n\n1️⃣ اختر المنتج في الموقع واضغط “Buy with USDT”.\n2️⃣ أدخل بريدك الإلكتروني وأكّده بالرمز المكوّن من 6 أرقام.\n3️⃣ أرسل المبلغ <b>بالضبط</b> على شبكة <b>BEP20 (BSC)</b> إلى العنوان الظاهر.\n4️⃣ يتم تأكيد الدفع تلقائياً وإرسال طلبك إلى بريدك.\n\n⚠️ شبكة BEP20 فقط. إذا سحبت من منصة تداول، أضف رسوم السحب ليصل المبلغ كاملاً.",
			'st_pending'   => '⏳ بانتظار الدفع',
			'st_paid'      => '✅ تم الدفع — قيد التجهيز',
			'st_delivered' => '📦 تم التسليم',
			'st_expired'   => '⌛ منتهي الصلاحية',
			'st_rejected'  => '❌ ملغى',
			'order'     => 'الطلب',
			'product'   => 'المنتج',
			'amount'    => 'المبلغ',
			'status'    => 'الحالة',
			'unknown'   => 'اختر من القائمة أدناه 👇',
			'n_paid'    => '✅ تم استلام دفعتك للطلب <code>%s</code>. جاري تجهيز طلبك.',
			'n_dlv'     => '📦 تم تسليم طلبك <code>%s</code>. تحقق من بريدك الإلكتروني (وكذلك مجلد الرسائل غير المرغوب فيها).',
			'subscribed'=> '🔔 سنخبرك هنا عند تحديث حالة الطلب.',
		);
		$fa += array(
			'n_paid'    => '✅ پرداخت سفارش <code>%s</code> دریافت شد. سفارش شما در حال آماده‌سازی است.',
			'n_dlv'     => '📦 سفارش <code>%s</code> تحویل شد. ایمیل خود را ببینید (پوشه Spam را هم چک کنید).',
			'subscribed'=> '🔔 هر تغییری در وضعیت این سفارش همین‌جا به شما اطلاع داده می‌شود.',
		);
		$en += array(
			'n_paid'    => '✅ Payment received for order <code>%s</code>. Your order is being prepared.',
			'n_dlv'     => '📦 Your order <code>%s</code> has been delivered. Check your email (and the spam folder).',
			'subscribed'=> '🔔 We will notify you here when the order status changes.',
		);
		$set = 'fa' === $lang ? $fa : ( 'ar' === $lang ? $ar : $en );
		return $set[ $key ] ?? ( $en[ $key ] ?? $key );
	}

	private static function products_text( $lang ) {
		$s = Mefta_Telegram_Notifier::settings();
		$custom = trim( (string) ( 'fa' === $lang ? $s['products_fa'] : $s['products_en'] ) );
		if ( '' !== $custom ) {
			return $custom;
		}
		if ( 'ar' === $lang ) {
			return "<b>🛒 منتجات MEFTACARD</b>\n\n🤖 <b>ChatGPT Plus</b> — 22 USDT\n🧠 <b>Claude Pro</b> — 24 USDT\n💻 <b>Cursor Pro</b> — 22 USDT\n🔑 <b>مفتاح API لـ Gemini / OpenRouter</b> — 10–100 USDT\n📱 <b>رقم افتراضي Non-VoIP</b> — من 0.95 USDT\n💳 <b>بطاقة افتراضية 3D Secure</b> — شحن 25–250 USDT\n\nالدفع: USDT على شبكة BEP20 فقط.";
		}
		if ( 'fa' === $lang ) {
			return "<b>🛒 محصولات MEFTACARD</b>\n\n🤖 <b>ChatGPT Plus</b> — 22 USDT\n🧠 <b>Claude Pro</b> — 24 USDT\n💻 <b>Cursor Pro</b> — 22 USDT\n🔑 <b>کلید API جمنای / OpenRouter</b> — 10 تا 100 USDT\n📱 <b>شماره مجازی Non-VoIP</b> — از 0.95 USDT\n💳 <b>کارت مجازی 3D Secure</b> — شارژ 25 تا 250 USDT\n\nپرداخت فقط با تتر روی شبکه BEP20.";
		}
		return "<b>🛒 MEFTACARD products</b>\n\n🤖 <b>ChatGPT Plus</b> — 22 USDT\n🧠 <b>Claude Pro</b> — 24 USDT\n💻 <b>Cursor Pro</b> — 22 USDT\n🔑 <b>Gemini / OpenRouter API key</b> — 10–100 USDT\n📱 <b>Non-VoIP virtual number</b> — from 0.95 USDT\n💳 <b>3D Secure virtual card</b> — load 25–250 USDT\n\nPayment: USDT on BEP20 only.";
	}

	private static function menu( $lang ) {
		return array(
			'keyboard'        => array(
				array( array( 'text' => self::t( $lang, 'products' ) ), array( 'text' => self::t( $lang, 'track' ) ) ),
				array( array( 'text' => self::t( $lang, 'howto' ) ), array( 'text' => self::t( $lang, 'support' ) ) ),
				array( array( 'text' => self::t( $lang, 'site' ) ) ),
			),
			'resize_keyboard' => true,
			'is_persistent'   => true,
		);
	}

	/** Which menu button (if any) the text matches, in either language. */
	private static function button( $text ) {
		foreach ( array( 'products', 'track', 'howto', 'support', 'site' ) as $k ) {
			if ( $text === self::t( 'fa', $k ) || $text === self::t( 'en', $k ) || $text === self::t( 'ar', $k ) ) {
				return $k;
			}
		}
		return '';
	}

	/* ================================================================ telegram api */

	public static function api( $method, $params, $blocking = true ) {
		$s = Mefta_Telegram_Notifier::settings();
		if ( '' === $s['token'] ) {
			return new WP_Error( 'no_token', 'Bot token is not set.' );
		}
		foreach ( $params as $k => $v ) {
			if ( is_array( $v ) ) {
				$params[ $k ] = wp_json_encode( $v );
			}
		}
		$r = wp_remote_post(
			'https://api.telegram.org/bot' . $s['token'] . '/' . $method,
			array( 'timeout' => $blocking ? 15 : 3, 'blocking' => $blocking, 'body' => $params )
		);
		if ( ! $blocking ) {
			return true;
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$b = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $b['ok'] ) ) {
			return new WP_Error( 'tg', $b['description'] ?? 'Telegram error' );
		}
		return $b['result'];
	}

	private static function say( $chat, $text, $markup = null, $extra = array() ) {
		if ( isset( $extra['reply_to_message_id'] ) ) {
			$extra['reply_parameters'] = array( 'message_id' => (int) $extra['reply_to_message_id'], 'allow_sending_without_reply' => true );
			unset( $extra['reply_to_message_id'] );
		}
		$p = array_merge(
			array( 'chat_id' => $chat, 'text' => mb_substr( $text, 0, 4000 ), 'parse_mode' => 'HTML', 'link_preview_options' => array( 'is_disabled' => true ) ),
			$extra
		);
		if ( $markup ) {
			$p['reply_markup'] = $markup;
		}
		$r = self::api( 'sendMessage', $p );
		// Broken HTML (e.g. an unclosed tag in custom text) → resend as plain text instead of failing silently.
		if ( is_wp_error( $r ) && false !== stripos( $r->get_error_message(), 'parse entities' ) ) {
			unset( $p['parse_mode'] );
			$p['text'] = mb_substr( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ), 0, 4000 );
			$r         = self::api( 'sendMessage', $p );
		}
		return $r;
	}

	/* ================================================================ webhook */

	public static function routes() {
		register_rest_route(
			'mefta-tg/v1',
			'/hook',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'verify' ),
				'callback'            => array( __CLASS__, 'hook' ),
			)
		);
	}

	public static function verify( WP_REST_Request $req ) {
		$secret = (string) get_option( self::SECRET_OPT );
		$got    = (string) $req->get_header( 'x_telegram_bot_api_secret_token' );
		return '' !== $secret && hash_equals( $secret, $got );
	}

	public static function hook( WP_REST_Request $req ) {
		$u = $req->get_json_params();
		// Telegram re-sends an update if we answer slowly; process each update only once.
		if ( isset( $u['update_id'] ) ) {
			$dk = 'mefta_tg_upd_' . (int) $u['update_id'];
			if ( get_transient( $dk ) ) {
				return new WP_REST_Response( array( 'ok' => true ) );
			}
			set_transient( $dk, 1, DAY_IN_SECONDS );
		}
		try {
			if ( isset( $u['callback_query'] ) ) {
				self::on_callback( $u['callback_query'] );
			} elseif ( isset( $u['message'] ) ) {
				self::on_message( $u['message'] );
			}
		} catch ( \Throwable $e ) {
			// Never let Telegram retry forever because of one bad update.
			error_log( 'Meftacard bot: ' . $e->getMessage() ); // phpcs:ignore
		}
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	private static function is_admin_chat( $chat_id ) {
		$admin = (string) Mefta_Telegram_Notifier::settings()['chat_id'];
		return '' !== $admin && (string) $chat_id === $admin;
	}

	/** Customer language: their saved choice, otherwise English (default). */
	private static function lang( $chat ) {
		$prefs = (array) get_option( 'mefta_tg_lang_prefs', array() );
		$l     = $prefs[ (string) $chat ] ?? 'en';
		return in_array( $l, array( 'en', 'fa', 'ar' ), true ) ? $l : 'en';
	}

	private static function set_lang( $chat, $lang ) {
		$prefs                    = (array) get_option( 'mefta_tg_lang_prefs', array() );
		$prefs[ (string) $chat ] = $lang;
		update_option( 'mefta_tg_lang_prefs', array_slice( $prefs, -20000, null, true ), false );
	}

	private static function lang_buttons() {
		return array(
			'inline_keyboard' => array(
				array(
					array( 'text' => '🇬🇧 English', 'callback_data' => 'lang:en' ),
					array( 'text' => '🇮🇷 فارسی', 'callback_data' => 'lang:fa' ),
					array( 'text' => '🇸🇦 العربية', 'callback_data' => 'lang:ar' ),
				),
			),
		);
	}

	private static function state( $chat, $value = null ) {
		$key = 'mefta_tg_state_' . md5( (string) $chat );
		if ( null === $value ) {
			return (string) get_transient( $key );
		}
		if ( '' === $value ) {
			delete_transient( $key );
		} else {
			set_transient( $key, $value, 30 * MINUTE_IN_SECONDS );
		}
		return $value;
	}

	/* ================================================================ messages */

	private static function on_message( $m ) {
		$chat  = $m['chat']['id'] ?? 0;
		$type  = $m['chat']['type'] ?? '';
		$text  = preg_replace( '/^(\/\w+)@\w+/', '$1', trim( (string) ( $m['text'] ?? ( $m['caption'] ?? '' ) ) ) );
		$lang  = self::lang( $chat );
		$admin = self::is_admin_chat( $chat );

		if ( ! $chat ) {
			return;
		}
		// In groups/channels only the admin chat is served (notifications + admin commands).
		if ( 'private' !== $type && ! $admin ) {
			return;
		}

		/* ---------- admin ---------- */
		if ( $admin ) {
			// Reply to a forwarded customer message → send back to that customer.
			if ( isset( $m['reply_to_message']['message_id'] ) ) {
				$target = get_transient( 'mefta_tg_rel_' . $m['reply_to_message']['message_id'] );
				if ( $target ) {
					self::say( $target['chat'], self::t( $target['lang'], 'sup_reply' ) );
					$r = self::api( 'copyMessage', array( 'chat_id' => $target['chat'], 'from_chat_id' => $chat, 'message_id' => $m['message_id'] ) );
					self::say( $chat, is_wp_error( $r ) ? '❌ ارسال نشد: ' . esc_html( $r->get_error_message() ) : '✅ پاسخ برای مشتری ارسال شد.', null, array( 'reply_to_message_id' => $m['message_id'] ) );
					return;
				}
			}
			// Awaiting delivery content for an order.
			$st = self::state( $chat );
			if ( 0 === strpos( $st, 'deliver:' ) && '' !== $text && '/' !== $text[0] ) {
				self::state( $chat, '' );
				self::deliver( (int) substr( $st, 8 ), $text, $chat );
				return;
			}
			if ( '/cancel' === $text ) {
				self::state( $chat, '' );
				self::say( $chat, 'لغو شد.' );
				return;
			}
			if ( '/orders' === $text ) {
				self::admin_list( $chat, array() );
				return;
			}
			if ( '/pending' === $text ) {
				self::admin_list( $chat, array( 'paid' ) );
				return;
			}
			if ( '/stats' === $text ) {
				self::admin_stats( $chat );
				return;
			}
			if ( '/help' === $text || '/admin' === $text ) {
				self::say( $chat, "<b>دستورات مدیریت</b>\n/orders — ۱۰ سفارش آخر\n/pending — پرداخت‌شده‌های تحویل‌نشده\n/stats — آمار فروش\n/cancel — لغو عملیات\n\nبرای جواب دادن به مشتری، روی پیام او <b>Reply</b> بزنید." );
				return;
			}
		}

		/* ---------- customer ---------- */
		if ( '/language' === $text || '/lang' === $text ) {
			self::say( $chat, '🌐 Choose your language / زبان / اللغة', self::lang_buttons() );
			return;
		}
		if ( 0 === strpos( $text, '/start' ) ) {
			self::state( $chat, '' );
			self::say( $chat, self::t( $lang, 'welcome' ), self::menu( $lang ) );
			self::say( $chat, '🌐 Language / زبان / اللغة', self::lang_buttons() );
			if ( $admin ) {
				self::say( $chat, '👑 شما مدیر هستید. /help را برای دستورات مدیریت بزنید.' );
			}
			return;
		}

		// Any other /command from a customer → show the menu (don't forward commands to support).
		if ( '' !== $text && '/' === $text[0] && ! $admin ) {
			self::state( $chat, '' );
			self::say( $chat, self::t( $lang, 'unknown' ), self::menu( $lang ) );
			return;
		}

		$btn = self::button( $text );
		if ( $btn ) {
			self::state( $chat, '' );
		}
		switch ( $btn ) {
			case 'products':
				self::say( $chat, self::products_text( $lang ), array( 'inline_keyboard' => array( array( array( 'text' => self::t( $lang, 'buy' ), 'url' => home_url( '/#ai-row' ) ) ) ) ) );
				return;
			case 'howto':
				self::say(
					$chat,
					self::t( $lang, 'howto_txt' ),
					array(
						'inline_keyboard' => array(
							array( array( 'text' => self::t( $lang, 'video' ), 'url' => home_url( 'fa' === $lang ? '/buy-gemini-api-and-claude-number-usdt/' : '/pay-chatgpt-claude-cursor-with-usdt/' ) ) ),
							array( array( 'text' => self::t( $lang, 'buy' ), 'url' => home_url( '/' ) ) ),
						),
					)
				);
				return;
			case 'site':
				self::say( $chat, '🌐 <a href="' . esc_url( home_url( '/' ) ) . '">meftacard.com</a>', array( 'inline_keyboard' => array( array( array( 'text' => self::t( $lang, 'buy' ), 'url' => home_url( '/' ) ) ) ) ) );
				return;
			case 'track':
				self::state( $chat, 'track' );
				self::say( $chat, self::t( $lang, 'ask_order' ) );
				return;
			case 'support':
				self::state( $chat, 'support' );
				self::say( $chat, self::t( $lang, 'ask_sup' ) );
				return;
		}

		// An order ID anywhere in the text → show status.
		if ( preg_match( '/\bMC-[A-Z0-9]{8}\b/i', $text, $mm ) ) {
			self::state( $chat, '' );
			self::track( $chat, strtoupper( $mm[0] ), $lang );
			return;
		}

		$st = self::state( $chat );
		if ( 'track' === $st ) {
			self::say( $chat, self::t( $lang, 'not_found' ) );
			return;
		}
		if ( 'support' === $st || ! $admin ) {
			// Everything else from a customer goes to support (max 20 messages/hour per chat).
			$rk = 'mefta_tg_rl_' . md5( (string) $chat );
			$rn = (int) get_transient( $rk );
			if ( $rn >= 20 ) {
				return;
			}
			set_transient( $rk, $rn + 1, HOUR_IN_SECONDS );
			self::relay_to_admin( $m, $lang );
			self::say( $chat, self::t( $lang, 'sup_sent' ) );
			return;
		}
		self::say( $chat, self::t( $lang, 'unknown' ), self::menu( $lang ) );
	}

	private static function relay_to_admin( $m, $lang ) {
		$admin = Mefta_Telegram_Notifier::settings()['chat_id'];
		if ( '' === (string) $admin ) {
			return;
		}
		$f    = $m['from'] ?? array();
		$name = trim( ( $f['first_name'] ?? '' ) . ' ' . ( $f['last_name'] ?? '' ) );
		$user = isset( $f['username'] ) ? ' @' . $f['username'] : '';
		$head = self::say( $admin, '💬 <b>پیام مشتری</b>: ' . esc_html( $name . $user ) . "\n<i>برای پاسخ، روی پیام زیر Reply بزنید.</i>" );
		$copy = self::api( 'copyMessage', array( 'chat_id' => $admin, 'from_chat_id' => $m['chat']['id'], 'message_id' => $m['message_id'] ) );
		$rel  = array( 'chat' => $m['chat']['id'], 'lang' => $lang );
		foreach ( array( $head, $copy ) as $r ) {
			if ( ! is_wp_error( $r ) && isset( $r['message_id'] ) ) {
				set_transient( 'mefta_tg_rel_' . $r['message_id'], $rel, 14 * DAY_IN_SECONDS );
			}
		}
	}

	/* ================================================================ orders */

	private static function find_order( $order_id ) {
		if ( ! post_type_exists( self::ORDER_CPT ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'   => self::ORDER_CPT,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => '_dp_order_id', 'value' => $order_id ) ),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private static function om( $id, $k ) {
		return (string) get_post_meta( $id, '_dp_' . $k, true );
	}

	private static function status_label( $id, $lang ) {
		$st = self::om( $id, 'status' );
		return self::t( $lang, 'st_' . ( $st ?: 'pending' ) );
	}

	private static function track( $chat, $order_id, $lang ) {
		$id = self::find_order( $order_id );
		if ( ! $id ) {
			self::say( $chat, self::t( $lang, 'not_found' ) );
			return;
		}
		$amount = self::om( $id, 'paid_amount' ) ?: self::om( $id, 'pay_amount' );
		$st     = self::om( $id, 'status' );
		$sub    = in_array( $st, array( 'pending', 'paid' ), true );
		if ( $sub ) {
			update_post_meta( $id, '_dp_tg_chat', (string) $chat );
			update_post_meta( $id, '_dp_tg_lang', $lang );
		}
		self::say(
			$chat,
			'🧾 <b>' . self::t( $lang, 'order' ) . '</b> <code>' . esc_html( $order_id ) . "</code>\n" .
			( $sub ? self::t( $lang, 'subscribed' ) . "\n\n" : '' ) .
			'📦 ' . self::t( $lang, 'product' ) . ': ' . esc_html( self::om( $id, 'product' ) ) . "\n" .
			'💵 ' . self::t( $lang, 'amount' ) . ': <code>' . esc_html( $amount ) . "</code> USDT\n" .
			'📍 ' . self::t( $lang, 'status' ) . ': <b>' . self::status_label( $id, $lang ) . '</b>'
		);
	}

	/** Admin card for one order (used by paid notifications and /orders). */
	public static function admin_card( $id ) {
		$oid    = self::om( $id, 'order_id' );
		$st     = self::om( $id, 'status' );
		$amount = self::om( $id, 'paid_amount' ) ?: self::om( $id, 'pay_amount' );
		$txt    = '🧾 <b>' . esc_html( $oid ) . '</b> — ' . self::status_label( $id, 'fa' ) . "\n";
		$txt   .= '📦 ' . esc_html( self::om( $id, 'product' ) ) . "\n";
		$txt   .= '💵 <code>' . esc_html( $amount ) . '</code> USDT (انتظار: ' . esc_html( self::om( $id, 'pay_amount' ) ) . ")\n";
		$txt   .= '📧 ' . esc_html( self::om( $id, 'email' ) );
		if ( 'tolerance' === self::om( $id, 'match' ) ) {
			$txt .= "\n⚠️ مبلغ دقیق نبود (مبلغ نزدیک) — قبل از تحویل چک کنید.";
		}
		$tx = self::om( $id, 'tx' );
		$rows = array();
		if ( 'paid' === $st ) {
			$rows[] = array(
				array( 'text' => '📤 ارسال محصول', 'callback_data' => 'dlv:' . $id ),
				array( 'text' => '❌ رد', 'callback_data' => 'rej:' . $id ),
			);
		}
		$links = array( array( 'text' => '🖥 در سایت', 'url' => admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
		if ( $tx ) {
			$links[] = array( 'text' => '🔗 تراکنش', 'url' => 'https://bscscan.com/tx/' . $tx );
		}
		$rows[] = $links;
		return array( $txt, array( 'inline_keyboard' => $rows ) );
	}

	private static function admin_list( $chat, $statuses ) {
		$q = array(
			'post_type'   => self::ORDER_CPT,
			'post_status' => 'publish',
			'numberposts' => 10,
			'fields'      => 'ids',
		);
		if ( $statuses ) {
			$q['meta_query'] = array( array( 'key' => '_dp_status', 'value' => $statuses, 'compare' => 'IN' ) );
		}
		$ids = post_type_exists( self::ORDER_CPT ) ? get_posts( $q ) : array();
		if ( ! $ids ) {
			self::say( $chat, $statuses ? '✅ سفارش تحویل‌نشده‌ای نیست.' : 'هنوز سفارشی ثبت نشده.' );
			return;
		}
		foreach ( $ids as $id ) {
			list( $txt, $kb ) = self::admin_card( $id );
			self::say( $chat, $txt, $kb );
		}
	}

	private static function admin_stats( $chat ) {
		if ( ! post_type_exists( self::ORDER_CPT ) ) {
			self::say( $chat, 'افزونه پرداخت مستقیم فعال نیست.' );
			return;
		}
		$ids = get_posts(
			array(
				'post_type'   => self::ORDER_CPT,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array( array( 'key' => '_dp_status', 'value' => array( 'paid', 'delivered' ), 'compare' => 'IN' ) ),
			)
		);
		$now = time();
		$agg = array( 'today' => array( 0, 0 ), 'week' => array( 0, 0 ), 'all' => array( 0, 0 ) );
		$today_start = strtotime( wp_date( 'Y-m-d 00:00:00' ) . ' ' . wp_timezone_string() );
		$waiting = 0;
		foreach ( $ids as $id ) {
			$amt = (float) ( self::om( $id, 'paid_amount' ) ?: self::om( $id, 'pay_amount' ) );
			$t   = (int) self::om( $id, 'paid_at' );
			$agg['all'][0]++;
			$agg['all'][1] += $amt;
			if ( $t >= $now - 7 * DAY_IN_SECONDS ) {
				$agg['week'][0]++;
				$agg['week'][1] += $amt;
			}
			if ( $t >= $today_start ) {
				$agg['today'][0]++;
				$agg['today'][1] += $amt;
			}
			if ( 'paid' === self::om( $id, 'status' ) ) {
				$waiting++;
			}
		}
		$f = function ( $a ) {
			return $a[0] . ' سفارش — <b>' . number_format( $a[1], 2 ) . '</b> USDT';
		};
		self::say( $chat, "📊 <b>آمار فروش</b>\n\nامروز: " . $f( $agg['today'] ) . "\n۷ روز اخیر: " . $f( $agg['week'] ) . "\nکل: " . $f( $agg['all'] ) . "\n\n📤 منتظر تحویل: <b>{$waiting}</b> (/pending)" );
	}

	/* ================================================================ callbacks */

	private static function on_callback( $cb ) {
		$chat = $cb['message']['chat']['id'] ?? 0;
		$data = (string) ( $cb['data'] ?? '' );
		if ( preg_match( '/^lang:(en|fa|ar)$/', $data, $lm ) ) {
			self::set_lang( $chat, $lm[1] );
			self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'], 'text' => '✓' ), false );
			self::say( $chat, self::t( $lm[1], 'welcome' ), self::menu( $lm[1] ) );
			return;
		}
		if ( ! self::is_admin_chat( $chat ) ) {
			self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'] ), false );
			return;
		}
		list( $act, $id ) = array_pad( explode( ':', $data, 2 ), 2, '' );
		$id = (int) $id;
		if ( ! $id || self::ORDER_CPT !== get_post_type( $id ) ) {
			self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'], 'text' => 'سفارش پیدا نشد' ), false );
			return;
		}
		$st = self::om( $id, 'status' );
		if ( 'dlv' === $act ) {
			if ( 'paid' !== $st ) {
				self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'], 'text' => 'این سفارش در وضعیت پرداخت‌شده نیست.' ), false );
				return;
			}
			self::state( $chat, 'deliver:' . $id );
			self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'] ), false );
			self::say( $chat, '📤 محصول سفارش <b>' . esc_html( self::om( $id, 'order_id' ) ) . "</b> را بنویسید (مثلاً اطلاعات اکانت یا کلید API).\nهمین متن برای مشتری ایمیل می‌شود و سفارش «تحویل شد» می‌شود.\n\nبرای لغو: /cancel", array( 'force_reply' => true, 'input_field_placeholder' => 'اطلاعات محصول...' ) );
			return;
		}
		if ( 'rej' === $act ) {
			if ( ! in_array( $st, array( 'paid', 'pending' ), true ) ) {
				self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'], 'text' => 'قابل رد نیست.' ), false );
				return;
			}
			update_post_meta( $id, '_dp_status', 'rejected' );
			self::api( 'answerCallbackQuery', array( 'callback_query_id' => $cb['id'], 'text' => 'سفارش رد شد' ), false );
			list( $txt, $kb ) = self::admin_card( $id );
			self::api( 'editMessageText', array( 'chat_id' => $chat, 'message_id' => $cb['message']['message_id'], 'text' => $txt, 'parse_mode' => 'HTML', 'reply_markup' => $kb ) );
			self::say( $chat, "ℹ️ اگر مشتری پرداخت کرده، بازپرداخت را دستی انجام دهید و به او اطلاع دهید.\nایمیل: " . esc_html( self::om( $id, 'email' ) ) );
		}
	}

	private static function deliver( $id, $content, $chat ) {
		if ( self::ORDER_CPT !== get_post_type( $id ) || 'paid' !== self::om( $id, 'status' ) ) {
			self::say( $chat, '❌ این سفارش قابل تحویل نیست (شاید قبلاً تحویل شده).' );
			return;
		}
		$oid   = self::om( $id, 'order_id' );
		$email = self::om( $id, 'email' );
		$body  = "Hello,\n\nYour order {$oid} has been delivered.\n\nProduct: " . self::om( $id, 'product' ) . "\n\n";
		$body .= "------------------------------\n{$content}\n------------------------------\n\n";
		$body .= "Please keep this information private.\nNeed help? Reply to this email or message us on Telegram: https://t.me/meftacard\n\n— MEFTACARD\nhttps://meftacard.com\n";
		if ( ! wp_mail( $email, "Your order {$oid} is ready – MEFTACARD", $body ) ) {
			self::say( $chat, '❌ ایمیل ارسال نشد. وضعیت سفارش تغییر نکرد؛ دوباره تلاش کنید.' );
			return;
		}
		update_post_meta( $id, '_dp_status', 'delivered' );
		update_post_meta( $id, '_dp_delivered_at', time() );
		// Keep a copy for support (visible only in wp-admin).
		update_post_meta( $id, '_dp_delivery', $content );
		self::notify_customer( $id, 'n_dlv' );
		self::say( $chat, '✅ سفارش <b>' . esc_html( $oid ) . '</b> تحویل شد و برای ' . esc_html( $email ) . ' ایمیل شد.' );
	}

	/** Tell a customer who tracked this order in the bot about a status change. */
	public static function notify_customer( $id, $key ) {
		$c = self::om( $id, 'tg_chat' );
		if ( '' === $c || ! get_option( self::SECRET_OPT ) ) {
			return;
		}
		$lang = self::om( $id, 'tg_lang' ) ?: 'en';
		self::say( $c, sprintf( self::t( $lang, $key ), esc_html( self::om( $id, 'order_id' ) ) ) );
	}

	/* ================================================================ webhook setup */

	public static function handle_activate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'mefta_tg_webhook' );
		$secret = wp_generate_password( 48, false, false );
		update_option( self::SECRET_OPT, $secret, false );
		$r = self::api(
			'setWebhook',
			array(
				'url'                  => rest_url( 'mefta-tg/v1/hook' ),
				'secret_token'         => $secret,
				'allowed_updates'      => array( 'message', 'callback_query' ),
				'drop_pending_updates' => 'true',
			)
		);
		if ( ! is_wp_error( $r ) ) {
			self::api( 'setMyCommands', array( 'commands' => array( array( 'command' => 'start', 'description' => 'Main menu' ), array( 'command' => 'language', 'description' => 'Change language' ) ) ) );
			$admin = Mefta_Telegram_Notifier::settings()['chat_id'];
			if ( preg_match( '/^-?\d+$/', (string) $admin ) ) {
				self::api(
					'setMyCommands',
					array(
						'commands' => array(
							array( 'command' => 'orders', 'description' => '۱۰ سفارش آخر' ),
							array( 'command' => 'pending', 'description' => 'منتظر تحویل' ),
							array( 'command' => 'stats', 'description' => 'آمار فروش' ),
							array( 'command' => 'help', 'description' => 'راهنمای مدیریت' ),
							array( 'command' => 'start', 'description' => 'منوی اصلی' ),
							array( 'command' => 'language', 'description' => 'تغییر زبان' ),
						),
						'scope'    => array( 'type' => 'chat', 'chat_id' => (int) $admin ),
					)
				);
			}
		}
		Mefta_Telegram_Notifier::back( is_wp_error( $r ) ? 'فعال‌سازی ربات ناموفق: ' . $r->get_error_message() : 'ربات فعال شد ✅ حالا در تلگرام /start بزنید.' );
	}

	public static function handle_deactivate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		check_admin_referer( 'mefta_tg_webhook_off' );
		$r = self::api( 'deleteWebhook', array() );
		delete_option( self::SECRET_OPT );
		Mefta_Telegram_Notifier::back( is_wp_error( $r ) ? 'خطا: ' . $r->get_error_message() : 'ربات غیرفعال شد (اعلان‌ها همچنان ارسال می‌شوند).' );
	}

	public static function webhook_info() {
		if ( ! get_option( self::SECRET_OPT ) ) {
			return null;
		}
		$r = self::api( 'getWebhookInfo', array() );
		return is_wp_error( $r ) ? null : $r;
	}
}
