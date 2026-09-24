<?php
/**
 * One-click content importer: Tools > محتوای آسفالت با ما.
 *
 * Publishes the articles and pages in content/manifest.json using the
 * logged-in administrator's session, so it needs no REST API access or
 * Application Password (the host strips the Authorization header and
 * blocks /wp-json/wp/v2/users). Safe to run more than once: items are
 * matched by slug and updated instead of duplicated.
 *
 * @package AsfaltbamaChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Load the content manifest.
 *
 * @return array|null
 */
function asfaltbama_importer_manifest() {
	$file = ASFALTBAMA_CHILD_PATH . '/content/manifest.json';
	if ( ! is_readable( $file ) ) {
		return null;
	}

	$data = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return is_array( $data ) ? $data : null;
}

/**
 * Read a content file relative to content/.
 *
 * @param string $rel Relative path.
 *
 * @return string
 */
function asfaltbama_importer_read( $rel ) {
	$file = realpath( ASFALTBAMA_CHILD_PATH . '/content/' . $rel );
	$base = realpath( ASFALTBAMA_CHILD_PATH . '/content' );
	if ( ! $file || ! $base || 0 !== strpos( $file, $base ) || ! is_readable( $file ) ) {
		return '';
	}

	return (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/**
 * Find a post or page by slug in any non-trashed status.
 *
 * @param string $slug      Slug (Persian slugs are accepted).
 * @param string $post_type Post type.
 *
 * @return WP_Post|null
 */
function asfaltbama_importer_find( $slug, $post_type ) {
	$found = get_posts(
		[
			'name'             => $slug,
			'post_type'        => $post_type,
			'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'numberposts'      => 1,
			'suppress_filters' => true,
		]
	);

	return $found ? $found[0] : null;
}

/**
 * Create or update a post/page by slug.
 *
 * @param string $post_type Post type.
 * @param string $slug      Slug.
 * @param array  $fields    wp_insert_post fields.
 * @param array  $log       Log lines, appended to.
 * @param bool   $update    Whether to update an item that already exists.
 *
 * @return int Post ID, 0 on failure or when an existing item is skipped.
 */
function asfaltbama_importer_upsert( $post_type, $slug, $fields, &$log, $update = true ) {
	$existing = asfaltbama_importer_find( $slug, $post_type );
	$fields   = array_merge( $fields, [ 'post_type' => $post_type, 'post_name' => $slug ] );

	if ( $existing && ! $update ) {
		$log[] = sprintf( '✅ /%s/ از قبل وجود دارد؛ دست نخورد', $slug );
		return 0;
	}

	if ( $existing ) {
		unset( $fields['post_status'], $fields['post_author'] );
		$fields['ID'] = $existing->ID;
		$result       = wp_update_post( wp_slash( $fields ), true );
		$verb         = 'به‌روزرسانی شد';
	} else {
		$result = wp_insert_post( wp_slash( $fields ), true );
		$verb   = 'ساخته شد';
	}

	if ( is_wp_error( $result ) ) {
		$log[] = sprintf( '❌ /%s/: %s', $slug, $result->get_error_message() );
		return 0;
	}

	$log[] = sprintf( '✅ /%s/ %s', $slug, $verb );
	return (int) $result;
}

/**
 * Store Rank Math title, description and focus keyword.
 *
 * @param int   $post_id Post ID.
 * @param array $item    Manifest item.
 *
 * @return void
 */
function asfaltbama_importer_seo( $post_id, $item ) {
	$map = [
		'seo_title'       => 'rank_math_title',
		'seo_description' => 'rank_math_description',
		'focus_keyword'   => 'rank_math_focus_keyword',
	];

	foreach ( $map as $key => $meta_key ) {
		if ( ! empty( $item[ $key ] ) ) {
			update_post_meta( $post_id, $meta_key, wp_slash( $item[ $key ] ) );
		}
	}
}

/**
 * Run the import.
 *
 * @param array $manifest        Manifest.
 * @param bool  $update_existing Overwrite items that already exist. The
 *                               automatic run passes false, so edits made
 *                               in WordPress are never reset.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_run( $manifest, $update_existing = true ) {
	$log = [];

	// 1. About page -> /about-us/.
	$about = $manifest['about_page'];
	if ( asfaltbama_importer_find( $about['new_slug'], 'page' ) ) {
		$log[] = '✅ /' . $about['new_slug'] . '/ از قبل وجود دارد';
	} else {
		$page = asfaltbama_importer_find( $about['current_slug'], 'page' );
		if ( $page ) {
			$result = wp_update_post(
				[
					'ID'        => $page->ID,
					'post_name' => $about['new_slug'],
				],
				true
			);
			$log[] = is_wp_error( $result )
				? '❌ درباره ما: ' . $result->get_error_message()
				: '✅ صفحه‌ی درباره ما به /' . $about['new_slug'] . '/ منتقل شد';
		} else {
			$log[] = '⚠️ صفحه‌ی درباره ما پیدا نشد';
		}
	}

	// 2. Articles page, used as the posts page.
	$pp          = $manifest['posts_page'];
	$articles_id = asfaltbama_importer_upsert(
		'page',
		$pp['slug'],
		[
			'post_title'   => $pp['title'],
			'post_content' => '',
			'post_status'  => 'publish',
		],
		$log,
		$update_existing
	);
	if ( $articles_id ) {
		asfaltbama_importer_seo( $articles_id, $pp );
		if ( (int) get_option( 'page_for_posts' ) !== $articles_id ) {
			update_option( 'page_for_posts', $articles_id );
			$log[] = '✅ «' . $pp['title'] . '» به‌عنوان برگه‌ی نوشته‌ها تنظیم شد';
		}
	}

	// 3. Pages.
	foreach ( $manifest['pages'] as $item ) {
		$id = asfaltbama_importer_upsert(
			'page',
			$item['slug'],
			[
				'post_title'   => $item['title'],
				'post_content' => asfaltbama_importer_read( $item['file'] ),
				'post_excerpt' => $item['excerpt'] ?? '',
				'post_status'  => 'publish',
			],
			$log,
			$update_existing
		);
		if ( $id ) {
			asfaltbama_importer_seo( $id, $item );
		}
	}

	// 4. Posts.
	foreach ( $manifest['posts'] as $item ) {
		$category = get_category_by_slug( $item['category'] );
		$id       = asfaltbama_importer_upsert(
			'post',
			$item['slug'],
			[
				'post_title'    => $item['title'],
				'post_content'  => asfaltbama_importer_read( $item['file'] ),
				'post_excerpt'  => $item['excerpt'],
				'post_status'   => 'publish',
				'post_author'   => get_current_user_id(),
				'post_category' => $category ? [ $category->term_id ] : [],
			],
			$log,
			$update_existing
		);
		if ( $id ) {
			asfaltbama_importer_seo( $id, $item );
		}
	}

	return $log;
}

/**
 * Fill empty image alt texts listed in the manifest (attachment ID => alt).
 * Never overwrites an alt text that is already set.
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_media_alt( $manifest ) {
	$log = [];
	foreach ( (array) ( $manifest['media_alt'] ?? [] ) as $id => $alt ) {
		$id = (int) $id;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			$log[] = '⚠️ تصویر #' . $id . ' پیدا نشد';
			continue;
		}
		if ( '' !== trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
			$log[] = '✅ تصویر #' . $id . ' از قبل متن جایگزین دارد';
			continue;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $alt ) ) );
		$log[] = '✅ متن جایگزین تصویر #' . $id . ' ثبت شد: ' . $alt;
	}

	return $log;
}

/**
 * Set Rank Math title/description on existing pages (by slug; the key
 * "__front__" means the static front page). Overwrites on purpose: the
 * manifest holds the optimized values.
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_page_seo( $manifest ) {
	$log = [];
	foreach ( (array) ( $manifest['page_seo'] ?? [] ) as $slug => $seo ) {
		$page = '__front__' === $slug ? get_post( (int) get_option( 'page_on_front' ) ) : asfaltbama_importer_find( $slug, 'page' );
		$name = '__front__' === $slug ? 'صفحه‌ی اصلی' : '/' . $slug . '/';
		if ( ! $page ) {
			$log[] = '⚠️ ' . $name . ' پیدا نشد';
			continue;
		}
		asfaltbama_importer_seo( $page->ID, $seo );
		$log[] = '✅ عنوان و توضیحات ' . $name . ' به‌روز شد';
	}

	return $log;
}

/**
 * Run the import automatically, once per content_version, when an
 * administrator loads the dashboard.
 *
 * Both the POST form and the nonce link reached WordPress without their
 * parameters on this host, so the import never ran. This path needs no
 * request data at all. The result is shown as an admin notice.
 *
 * @return void
 */
function asfaltbama_importer_auto_run() {
	if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$manifest = asfaltbama_importer_manifest();
	if ( ! $manifest ) {
		return;
	}

	$log = [];

	// Each step is versioned separately, and marked before it runs so a
	// failure cannot re-run on every admin page load.
	if ( ! empty( $manifest['content_version'] ) && get_option( 'asfaltbama_content_version' ) !== $manifest['content_version'] ) {
		update_option( 'asfaltbama_content_version', $manifest['content_version'], false );
		$log = array_merge( $log, asfaltbama_importer_run( $manifest, false ) );
	}

	if ( ! empty( $manifest['media_alt_version'] ) && get_option( 'asfaltbama_media_alt_version' ) !== $manifest['media_alt_version'] ) {
		update_option( 'asfaltbama_media_alt_version', $manifest['media_alt_version'], false );
		$log = array_merge( $log, asfaltbama_importer_media_alt( $manifest ) );
	}

	if ( ! empty( $manifest['page_seo_version'] ) && get_option( 'asfaltbama_page_seo_version' ) !== $manifest['page_seo_version'] ) {
		update_option( 'asfaltbama_page_seo_version', $manifest['page_seo_version'], false );
		$log = array_merge( $log, asfaltbama_importer_page_seo( $manifest ) );
	}

	if ( $log ) {
		update_option( 'asfaltbama_content_log', $log, false );
		set_transient( 'asfaltbama_content_notice', 1, HOUR_IN_SECONDS );
	}
}
add_action( 'admin_init', 'asfaltbama_importer_auto_run' );

/**
 * Show the result of the automatic import once.
 *
 * @return void
 */
function asfaltbama_importer_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'asfaltbama_content_notice' ) ) {
		return;
	}
	delete_transient( 'asfaltbama_content_notice' );

	echo '<div class="notice notice-success is-dismissible" dir="rtl" style="text-align:right"><p><strong>محتوای آسفالت با ما منتشر شد:</strong></p><ul>';
	foreach ( (array) get_option( 'asfaltbama_content_log', [] ) as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'asfaltbama_importer_notice' );

/**
 * Register the admin page under Tools.
 *
 * @return void
 */
function asfaltbama_importer_menu() {
	add_management_page(
		'محتوای آسفالت با ما',
		'محتوای آسفالت با ما',
		'manage_options',
		'asfaltbama-content',
		'asfaltbama_importer_page'
	);
}
add_action( 'admin_menu', 'asfaltbama_importer_menu' );

/**
 * Render the admin page and handle the import request.
 *
 * @return void
 */
function asfaltbama_importer_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$manifest = asfaltbama_importer_manifest();
	$log      = [];

	// A nonce-protected link rather than a POST form: on this host the form
	// submission arrived without its fields, so the import never ran.
	if ( $manifest && isset( $_REQUEST['asfaltbama_import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'asfaltbama_import' );
		$log = asfaltbama_importer_run( $manifest );
	}

	echo '<div class="wrap" dir="rtl" style="text-align:right">';
	echo '<h1>محتوای آسفالت با ما</h1>';

	if ( ! $manifest ) {
		echo '<div class="notice notice-error"><p>فایل content/manifest.json در قالب فرزند پیدا نشد.</p></div></div>';
		return;
	}

	if ( $log ) {
		echo '<div class="notice notice-success"><p><strong>انجام شد:</strong></p><ul>';
		foreach ( $log as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
	}

	echo '<p>این ابزار مقاله‌ها و برگه‌های آماده‌شده در قالب فرزند را منتشر می‌کند. اجرای دوباره، موارد موجود را به‌روز می‌کند و چیزی تکراری نمی‌سازد.</p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>عنوان</th><th>آدرس</th><th>وضعیت فعلی</th></tr></thead><tbody>';

	$rows = [
		[ $manifest['posts_page']['title'], $manifest['posts_page']['slug'], 'page' ],
	];
	foreach ( $manifest['pages'] as $item ) {
		$rows[] = [ $item['title'], $item['slug'], 'page' ];
	}
	foreach ( $manifest['posts'] as $item ) {
		$rows[] = [ $item['title'], $item['slug'], 'post' ];
	}

	foreach ( $rows as $row ) {
		$existing = asfaltbama_importer_find( $row[1], $row[2] );
		$status   = $existing ? 'موجود (' . $existing->post_status . ') — به‌روز می‌شود' : 'ساخته می‌شود';
		printf(
			'<tr><td>%s</td><td dir="ltr" style="text-align:right">/%s/</td><td>%s</td></tr>',
			esc_html( $row[0] ),
			esc_html( $row[1] ),
			esc_html( $status )
		);
	}
	echo '</tbody></table>';

	echo '<p>همچنین صفحه‌ی «درباره ما» به <code>/' . esc_html( $manifest['about_page']['new_slug'] ) . '/</code> منتقل می‌شود و «' . esc_html( $manifest['posts_page']['title'] ) . '» برگه‌ی نوشته‌ها می‌شود. مقاله‌ها به نام شما منتشر می‌شوند.</p>';
	$url = wp_nonce_url(
		add_query_arg(
			[
				'page'              => 'asfaltbama-content',
				'asfaltbama_import' => 1,
			],
			admin_url( 'tools.php' )
		),
		'asfaltbama_import'
	);
	echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary button-hero">انتشار محتوا</a></p>';
	echo '</div>';
}
