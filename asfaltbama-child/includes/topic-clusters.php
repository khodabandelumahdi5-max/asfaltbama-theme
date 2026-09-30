<?php
/**
 * Topic clusters: the home page links to the seven main topics, each topic
 * page (a service page, or the price guide) links to every guide in its
 * family, and every guide links back to its topic page and its siblings.
 *
 * One list here drives all of it, so a new article only needs its slug
 * added to the right family.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The seven families, in display order. A post that appears in more than
 * one family belongs primarily to the first one listing it.
 *
 * url    Topic page path.
 * label  Short name.
 * anchor Keyword-rich link text for the topic page.
 * desc   One sentence for the home page card.
 * posts  Guide slugs, most important first.
 * faq    Questions people search for, answered on the service page.
 * schema Whether to emit FAQPage for them (only where the page has none).
 *
 * @return array<string,array>
 */
function asfaltbama_clusters() {
	return apply_filters(
		'asfaltbama_clusters',
		[
			'asphalt-paving'         => [
				'url'    => '/asphalt-paving/',
				'label'  => 'آسفالت‌کاری',
				'anchor' => 'آسفالت کاری در تهران و کرج',
				'desc'   => 'اجرای آسفالت گرم توپکا و بیندر برای حیاط، کوچه، پارکینگ، رمپ و محوطه با فینیشر و غلتک.',
				'posts'  => [ 'asphalt-yard-parking', 'alley-asphalt', 'topeka-vs-binder-asphalt', 'parking-ramp-asphalt', 'cold-vs-hot-asphalt', 'asphalt-paving-season', 'asphalt-milling', 'asphalt-over-concrete', 'reclaimed-asphalt-guide', 'asphalt-drainage-ponding', 'curb-installation-guide', 'site-landscaping-asphalt', 'asphalt-contract-checklist' ],
				'faq'    => [
					[ 'آسفالت کوچه وظیفه‌ی کیست؟', 'آسفالت معابر عمومی در اصل با شهرداری است و ساکنان می‌توانند با درخواست کتبی، و در صورت نیاز استشهاد محلی، پیگیری کنند. اگر ساکنان بخواهند زودتر یا با کیفیت بهتر آسفالت شود، می‌توانند با هماهنگی و مجوز شهرداری هزینه را خودشان بپردازند. مراحل کامل در <a href="/alley-asphalt/">آسفالت کوچه</a> آمده است.' ],
					[ 'ضخامت مناسب آسفالت حیاط و پارکینگ چقدر است؟', 'برای خودروهای سواری معمولاً یک لایه توپکا با ضخامت حدود ۴ تا ۵ سانتی‌متر کوبیده، روی زیرسازی کوبیده و قیرپاشی‌شده کافی است. برای تردد کامیون، لایه‌ی بیندر هم لازم می‌شود. جزئیات در <a href="/asphalt-yard-parking/">آسفالت حیاط و پارکینگ</a>.' ],
					[ 'آیا در هوای سرد می‌شود آسفالت اجرا کرد؟', 'آسفالت گرم در هوای سرد، مرطوب یا یخبندان به‌خوبی کوبیده نمی‌شود و دوام کمتری دارد؛ بهترین زمان، فصل‌های معتدل و خشک است. توضیح کامل در <a href="/asphalt-paving-season/">بهترین فصل آسفالت‌کاری</a>.' ],
					[ 'آسفالت سرد بهتر است یا آسفالت گرم؟', 'آسفالت سرد برای وصله‌کاری فوری و چاله‌های کوچک مناسب است؛ برای حیاط، کوچه و پارکینگ، آسفالت گرم دوام و مقاومت بسیار بیشتری دارد. مقایسه در <a href="/cold-vs-hot-asphalt/">آسفالت سرد یا گرم</a>.' ],
				],
			],
			'asphalt-price'          => [
				'url'    => '/asphalt-price-per-ton/',
				'label'  => 'قیمت آسفالت',
				'anchor' => 'قیمت آسفالت ۱۴۰۵',
				'desc'   => 'قیمت روز هر تن آسفالت توپکا و بیندر، تبدیل قیمت تنی به متری و روش مقایسه‌ی پیشنهادها.',
				'posts'  => [ 'asphalt-price-per-ton', 'asphalt-price-factors', 'asphalt-tonnage-calculation', 'asphalt-contract-checklist', 'asphalt-isogam-warranty-guide' ],
			],
			'isogam-waterproofing'   => [
				'url'    => '/isogam-waterproofing/',
				'label'  => 'ایزوگام، قیرگونی و قیر',
				'anchor' => 'اجرای ایزوگام پشت بام',
				'desc'   => 'نصب ایزوگام و قیرگونی بام، سرویس بهداشتی و فونداسیون با تست آب‌بندی و ضمانت کتبی، و فروش قیر.',
				'posts'  => [ 'isogam-price-guide', 'isogam-installation-steps', 'bitumen-roofing-vs-isogam', 'isogam-over-old-isogam', 'roof-leak-after-isogam', 'roof-waterproofing-methods', 'bitumen-roofing-steps', 'bathroom-waterproofing', 'foundation-waterproofing', 'roof-winter-checklist', 'bitumen-price', 'bitumen-types', 'asphalt-isogam-warranty-guide' ],
				'faq'    => [
					[ 'اجرای ایزوگام پشت بام متری چند است؟', 'قیمت اجرای ایزوگام به نوع و برند ایزوگام، متراژ، تعداد لایه‌ها، دورچینی و وضعیت سطح (نیاز به جمع‌آوری ایزوگام قدیمی یا اصلاح شیب) بستگی دارد و پس از بازدید اعلام می‌شود. عوامل قیمت و محاسبه‌ی تعداد رول در <a href="/isogam-price-guide/">قیمت ایزوگام</a> آمده است.' ],
					[ 'هزینه‌ی ایزوگام پشت بام به عهده‌ی کیست؟', 'در ساختمان‌های آپارتمانی، بام معمولاً جزو مشاعات است و هزینه‌ی تعمیر و نگهداری آن بین مالکان تقسیم می‌شود. در ملک اجاره‌ای، تعمیرات اساسی مثل عایق بام معمولاً با مالک است، مگر قرارداد اجاره ترتیب دیگری گذاشته باشد.' ],
					[ 'قیرگونی بهتر است یا ایزوگام؟', 'هر دو روش در جای درست دوام خوبی دارند؛ ایزوگام سریع‌تر و یکنواخت‌تر است و قیرگونی در جزئیات پیچیده و بعضی کاربردها برتری دارد. مقایسه‌ی کامل در <a href="/bitumen-roofing-vs-isogam/">قیرگونی یا ایزوگام</a>.' ],
					[ 'می‌شود ایزوگام را روی ایزوگام قبلی نصب کرد؟', 'فقط وقتی ایزوگام قبلی سالم، خشک و چسبیده به سطح باشد و شیب بام درست باشد؛ در غیر این صورت باید جمع شود. معیارهای تصمیم در <a href="/isogam-over-old-isogam/">ایزوگام روی ایزوگام قدیمی</a>.' ],
				],
			],
			'excavation-and-grading' => [
				'url'    => '/excavation-and-grading/',
				'label'  => 'خاکبرداری و گودبرداری',
				'anchor' => 'خاکبرداری و گودبرداری در تهران',
				'desc'   => 'خاکبرداری، گودبرداری ساختمانی، تسطیح زمین و آماده‌سازی بستر با بیل مکانیکی و لودر.',
				'posts'  => [ 'excavation-cost-guide', 'excavation-safety-guide', 'land-grading-guide', 'subgrade-preparation-compaction' ],
				'faq'    => [
					[ 'قیمت خاکبرداری هر متر مکعب چطور محاسبه می‌شود؟', 'مبنای معمول، حجم خاک برداشته‌شده بر حسب متر مکعب است و قیمت به جنس خاک، عمق، دسترسی ماشین‌آلات، فاصله‌ی حمل و محل تخلیه بستگی دارد. در پروژه‌های دولتی فهرست بها مرجع است و در پروژه‌های خصوصی قیمت پس از بازدید توافق می‌شود. روش محاسبه در <a href="/excavation-cost-guide/">قیمت خاکبرداری هر متر مکعب</a>.' ],
					[ 'خاکبرداری با بیل مکانیکی بهتر است یا لودر؟', 'بیل مکانیکی برای کندن در عمق و گودبرداری مناسب است و لودر برای جابه‌جایی و بارگیری خاک سست و تسطیح؛ در بیشتر پروژه‌ها ترکیبی از هر دو، و در فضاهای تنگ بابکت یا مینی‌بیل به کار می‌رود.' ],
					[ 'گودبرداری کنار ساختمان قدیمی چه خطری دارد؟', 'بدون طرح پایدارسازی، دیواره‌ی گود ممکن است ریزش کند و به ساختمان مجاور آسیب بزند. نظارت مهندس، اجرای مرحله‌ای و روش‌هایی مثل نیلینگ یا سازه‌ی نگهبان لازم است. توضیح در <a href="/excavation-safety-guide/">گودبرداری اصولی</a>.' ],
					[ 'تسطیح زمین یعنی چه؟', 'تسطیح یعنی برداشتن برآمدگی‌ها و پر کردن گودی‌ها تا زمین به تراز یا شیب طراحی‌شده برسد و برای ساخت، محوطه‌سازی یا آسفالت آماده شود. مراحل در <a href="/land-grading-guide/">تسطیح زمین</a>.' ],
				],
			],
			'demolition-scrap'       => [
				'url'    => '/demolition-scrap/',
				'label'  => 'تخریب و ضایعات آهن',
				'anchor' => 'تخریب ساختمان کلنگی و خرید ضایعات آهن',
				'desc'   => 'تخریب اصولی ساختمان کلنگی، بتنی و فلزی، حمل نخاله و خرید نقدی ضایعات آهن.',
				'posts'  => [ 'demolition-cost-guide', 'scrap-iron-selling-guide', 'demolition-permit-tehran', 'excavation-cost-guide' ],
				'schema' => true,
				'faq'    => [
					[ 'هزینه‌ی تخریب ساختمان کلنگی چقدر است؟', 'به متراژ و نوع سازه (آجری، بتنی یا فلزی)، روش تخریب، دسترسی، حمل نخاله و ارزش ضایعات آهن بستگی دارد؛ ارزش آهن‌آلات گاهی بخش قابل توجهی از هزینه را جبران می‌کند. عوامل قیمت در <a href="/demolition-cost-guide/">قیمت تخریب ساختمان</a>.' ],
					[ 'برای تخریب ساختمان در تهران چه مجوزی لازم است؟', 'تخریب باید پس از اخذ پروانه یا مجوز از شهرداری منطقه و با رعایت ضوابط ایمنی انجام شود. مدارک و مراحل در <a href="/demolition-permit-tehran/">مجوز تخریب ساختمان در تهران</a>.' ],
					[ 'ضایعات آهن ساختمان کیلویی چند خریده می‌شود؟', 'قیمت ضایعات آهن هر روز با بازار تغییر می‌کند و به نوع آهن (سنگین، سبک، میلگرد، پروفیل)، تمیزی و حجم بستگی دارد. برای قیمت روز تماس بگیرید؛ نکات فروش در <a href="/scrap-iron-selling-guide/">فروش ضایعات آهن ساختمانی</a>.' ],
					[ 'تخریب ساختمان چقدر طول می‌کشد؟', 'بسته به متراژ، تعداد طبقات، نوع سازه و محدودیت‌های محل، از چند روز تا چند هفته؛ برنامه‌ی دقیق پس از بازدید مشخص می‌شود.' ],
				],
			],
			'asphalt-joint-sealing'  => [
				'url'    => '/asphalt-joint-sealing/',
				'label'  => 'درزگیری و لکه‌گیری',
				'anchor' => 'درزگیری آسفالت با ماستیک گرم',
				'desc'   => 'درزگیری ترک‌های طولی و عرضی آسفالت با قیر پلیمری و ماستیک گرم، و لکه‌گیری اصولی.',
				'posts'  => [ 'asphalt-crack-sealing-guide', 'crack-sealing-cost', 'asphalt-patching-guide' ],
				'faq'    => [
					[ 'درزگیری آسفالت چیست؟', 'پر کردن و آب‌بندی ترک‌های آسفالت با ماستیک گرم یا قیر پلیمری، تا آب به لایه‌های زیرین نرسد و ترک‌ها گسترش پیدا نکنند. کی و چطور در <a href="/asphalt-crack-sealing-guide/">درزگیری ترک‌های آسفالت</a>.' ],
					[ 'قیمت درزگیری آسفالت چطور محاسبه می‌شود؟', 'معمولاً بر اساس طول ترک‌ها (متر طول)، عرض و عمق آن‌ها، نوع ماستیک و حجم کار. روش محاسبه در <a href="/crack-sealing-cost/">قیمت درزگیری آسفالت</a>.' ],
					[ 'فرق لکه‌گیری با درزگیری آسفالت چیست؟', 'درزگیری برای ترک‌های خطی است؛ لکه‌گیری یعنی برداشتن بخش آسیب‌دیده (چاله یا ترک‌های موزاییکی) و جایگزینی آن با آسفالت جدید. مراحل در <a href="/asphalt-patching-guide/">لکه‌گیری آسفالت</a>.' ],
					[ 'انواع ترک آسفالت کدام‌اند؟', 'ترک‌های طولی، عرضی، انعکاسی، بلوکی و موزاییکی (پوست سوسماری). سه نوع اول معمولاً با درزگیری کنترل می‌شوند، اما ترک موزاییکی نشانه‌ی ضعف بستر است و لکه‌گیری یا ترمیم عمیق‌تر لازم دارد.' ],
				],
			],
			'machinery-rental'       => [
				'url'    => '/machinery-rental/',
				'label'  => 'اجاره ماشین‌آلات',
				'anchor' => 'اجاره بابکت و ماشین‌آلات راه‌سازی',
				'desc'   => 'اجاره روزانه‌ی بابکت، مینی‌بیل، بیل مکانیکی، فینیشر و غلتک با اپراتور.',
				'posts'  => [ 'bobcat-rental-guide', 'excavation-cost-guide', 'land-grading-guide' ],
				'schema' => true,
				'faq'    => [
					[ 'اجاره‌ی بابکت روزانه است یا ساعتی؟', 'معمولاً روزانه (یک شیفت کاری) و با اپراتور؛ برای کارهای کوتاه گاهی ساعتی هم توافق می‌شود. هزینه‌ی رفت‌وبرگشت دستگاه هم باید مشخص باشد. عوامل نرخ اجاره در <a href="/bobcat-rental-guide/">اجاره بابکت</a>.' ],
					[ 'مینی‌لودر همان بابکت است؟', 'بله؛ «بابکت» نام تجاری یک سازنده است که در ایران برای همه‌ی مینی‌لودرها رایج شده است.' ],
					[ 'فینیشر و غلتک با اپراتور اجاره داده می‌شود؟', 'بله، فینیشر و غلتک با اپراتور ماهر اجاره داده می‌شوند؛ کار با این دستگاه‌ها تجربه می‌خواهد و کیفیت آسفالت به آن وابسته است.' ],
					[ 'برای خاکبرداری کوچک چه دستگاهی مناسب است؟', 'در حیاط، زیرزمین و فضاهای تنگ، بابکت و مینی‌بیل؛ برای حجم بیشتر، بیل مکانیکی و لودر. راهنمای انتخاب در <a href="/excavation-cost-guide/">قیمت خاکبرداری</a>.' ],
				],
			],
		]
	);
}

/**
 * Manifest posts by slug.
 *
 * @return array<string,array>
 */
function asfaltbama_cluster_post_items() {
	static $items = null;
	if ( null === $items ) {
		$items = [];
		foreach ( (array) ( asfaltbama_content_manifest()['posts'] ?? [] ) as $item ) {
			$items[ $item['slug'] ] = $item;
		}
	}
	return $items;
}

/**
 * The family a post belongs to (first one listing it).
 *
 * @param string $slug Post slug.
 * @return array|null Cluster with its key under 'key'.
 */
function asfaltbama_cluster_of_post( $slug ) {
	foreach ( asfaltbama_clusters() as $key => $cluster ) {
		if ( in_array( $slug, $cluster['posts'], true ) ) {
			return $cluster + [ 'key' => $key ];
		}
	}
	return null;
}

/**
 * Link list of a family's guides.
 *
 * @param array  $cluster Cluster.
 * @param string $skip    Slug to leave out.
 * @param int    $max     Maximum links (0 = all).
 * @return string
 */
function asfaltbama_cluster_links( $cluster, $skip = '', $max = 0 ) {
	$items = asfaltbama_cluster_post_items();
	$out   = '';
	$n     = 0;
	foreach ( $cluster['posts'] as $slug ) {
		if ( $slug === $skip || ! isset( $items[ $slug ] ) || $slug === trim( $cluster['url'], '/' ) ) {
			continue;
		}
		$out .= '<li><a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . esc_html( $items[ $slug ]['title'] ) . '</a></li>';
		if ( $max && ++$n >= $max ) {
			break;
		}
	}
	return $out ? '<ul class="abm-family__list">' . $out . '</ul>' : '';
}

/**
 * Home page: the seven families with their topic page and top guides.
 *
 * @param string $content Page content.
 * @return string
 */
function asfaltbama_home_families( $content ) {
	static $done = false;
	if ( $done || ! is_front_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$done = true;

	$html  = '<section class="abm-latest abm-families" aria-labelledby="abm-families-title"><div class="abm-wrap">';
	$html .= '<h2 id="abm-families-title" class="abm-section-title">خدمات آسفالت با ما و راهنمای هر خدمت</h2>';
	$html .= '<div class="abm-families__grid">';
	foreach ( asfaltbama_clusters() as $cluster ) {
		$html .= '<div class="abm-family">';
		$html .= '<h3 class="abm-family__title"><a href="' . esc_url( home_url( $cluster['url'] ) ) . '">' . esc_html( $cluster['anchor'] ) . '</a></h3>';
		$html .= '<p class="abm-family__desc">' . esc_html( $cluster['desc'] ) . '</p>';
		$html .= asfaltbama_cluster_links( $cluster, '', 4 );
		$html .= '<a class="abm-family__more" href="' . esc_url( home_url( $cluster['url'] ) ) . '">' . esc_html( $cluster['label'] ) . ' ←</a>';
		$html .= '</div>';
	}
	$html .= '</div></div></section>';

	return $content . $html;
}
add_filter( 'the_content', 'asfaltbama_home_families', 31 );

/**
 * Service page: every guide of its family, the searched questions and the
 * other families. Runs after the three article cards (priority 30).
 *
 * @param string $content Page content.
 * @return string
 */
function asfaltbama_service_family( $content ) {
	static $done = false;
	if ( $done || ! is_page() || is_front_page() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$slug     = get_post_field( 'post_name', get_queried_object_id() );
	$clusters = asfaltbama_clusters();
	if ( ! isset( $clusters[ $slug ] ) ) {
		return $content;
	}
	$done    = true;
	$cluster = $clusters[ $slug ];

	$html  = '<section class="abm-latest abm-family-page" id="guides"><div class="abm-wrap">';
	$html .= '<h2 class="abm-section-title">' . esc_html( 'همه‌ی راهنماهای ' . $cluster['label'] ) . '</h2>';
	$html .= asfaltbama_cluster_links( $cluster );

	if ( ! empty( $cluster['faq'] ) ) {
		$html .= '<h2 class="abm-section-title">' . esc_html( 'سؤال‌های پرجست‌وجو درباره‌ی ' . $cluster['label'] ) . '</h2><div class="abm-family__faq">';
		foreach ( $cluster['faq'] as $qa ) {
			$html .= '<details><summary>' . esc_html( $qa[0] ) . '</summary><p>' . wp_kses( $qa[1], [ 'a' => [ 'href' => [] ] ] ) . '</p></details>';
		}
		$html .= '</div>';
	}

	$html .= '<nav class="abm-areas abm-family__siblings" aria-label="خدمات دیگر"><span class="abm-areas__title">خدمات دیگر:</span>';
	foreach ( $clusters as $key => $other ) {
		if ( $key !== $slug ) {
			$html .= '<a href="' . esc_url( home_url( $other['url'] ) ) . '">' . esc_html( $other['anchor'] ) . '</a>';
		}
	}
	$html .= '</nav></div></section>';

	return $content . $html;
}
add_filter( 'the_content', 'asfaltbama_service_family', 32 );

/**
 * FAQPage for service pages whose Elementor content has none.
 *
 * @param array $data Schema entities.
 * @return array
 */
function asfaltbama_service_faq_schema( $data ) {
	if ( ! is_array( $data ) || ! is_page() ) {
		return $data;
	}
	$slug     = get_post_field( 'post_name', get_queried_object_id() );
	$clusters = asfaltbama_clusters();
	if ( empty( $clusters[ $slug ]['schema'] ) || empty( $clusters[ $slug ]['faq'] ) ) {
		return $data;
	}
	foreach ( $data as $entity ) {
		if ( is_array( $entity ) && in_array( 'FAQPage', (array) ( $entity['@type'] ?? [] ), true ) ) {
			return $data;
		}
	}
	$main = [];
	foreach ( $clusters[ $slug ]['faq'] as $qa ) {
		$main[] = [
			'@type'          => 'Question',
			'name'           => $qa[0],
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $qa[1] ),
			],
		];
	}
	$data['abmServiceFaq'] = [
		'@type'      => 'FAQPage',
		'mainEntity' => $main,
	];
	return $data;
}
add_filter( 'rank_math/json_ld', 'asfaltbama_service_faq_schema', 30 );

/**
 * Article: a line under the lead pointing to its topic page, and the
 * sibling guides at the end.
 *
 * @param string $content Post content.
 * @return string
 */
function asfaltbama_post_family( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$slug    = get_post_field( 'post_name', get_the_ID() );
	$cluster = asfaltbama_cluster_of_post( $slug );
	if ( ! $cluster ) {
		return $content;
	}

	$is_pillar = trim( $cluster['url'], '/' ) === $slug;
	if ( ! $is_pillar ) {
		$parent = '<p class="abm-family__parent">این راهنما بخشی از مجموعه‌ی <a href="' . esc_url( home_url( $cluster['url'] ) ) . '">' . esc_html( $cluster['anchor'] ) . '</a> است.</p>';
		$pos    = strpos( $content, '</p>' );
		$content = false === $pos ? $parent . $content : substr( $content, 0, $pos + 4 ) . "\n" . $parent . substr( $content, $pos + 4 );
	}

	$links = asfaltbama_cluster_links( $cluster, $slug );
	if ( $links ) {
		$content .= '<nav class="abm-family__more-guides" aria-label="راهنماهای هم‌خانواده"><p class="abm-family__heading">' . esc_html( 'راهنماهای دیگر ' . $cluster['label'] ) . '</p>' . $links;
		if ( ! $is_pillar ) {
			$content .= '<p><a href="' . esc_url( home_url( $cluster['url'] ) ) . '">' . esc_html( $cluster['anchor'] ) . ' ←</a></p>';
		}
		$content .= '</nav>';
	}
	return $content;
}
add_filter( 'the_content', 'asfaltbama_post_family', 25 );
