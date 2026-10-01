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
 * Whether a theme-managed page (manifest "managed": true) may be replaced
 * with a newer version of its file on an automatic run: only when nobody
 * has edited it in WordPress. The stored hash is the content the importer
 * last wrote; pages created before hashes existed count as unedited while
 * they have never been modified since publication.
 *
 * @param array  $item    Manifest item.
 * @param string $content New content.
 *
 * @return bool
 */
function asfaltbama_importer_may_refresh( $item, $content ) {
	if ( empty( $item['managed'] ) ) {
		return false;
	}
	$page = asfaltbama_importer_find( $item['slug'], 'page' );
	if ( ! $page || $page->post_content === $content ) {
		return false;
	}
	$hash = get_post_meta( $page->ID, '_asfaltbama_content_hash', true );
	if ( $hash ) {
		return md5( $page->post_content ) === $hash;
	}
	return $page->post_modified_gmt === $page->post_date_gmt;
}

/**
 * Whitespace-insensitive hash of post content, for recognising content
 * the importer wrote (tools/prev_hashes.py computes the same in Python).
 *
 * @param string $content Content.
 *
 * @return string
 */
function asfaltbama_importer_nhash( $content ) {
	return md5( (string) preg_replace( '/[ \t\n\r\f\x0B]+/', ' ', trim( (string) $content ) ) );
}

/**
 * Whether an existing article may be replaced with the new version of its
 * file: only when its content is still a version the theme shipped
 * (manifest prev_hashes, or the hash stored at the last import), so an
 * article edited in WordPress is never overwritten.
 *
 * @param array  $item    Manifest post.
 * @param string $content New content.
 * @param array  $log     Log lines, appended to.
 *
 * @return bool
 */
function asfaltbama_importer_may_refresh_post( $item, $content, &$log ) {
	$post = asfaltbama_importer_find( $item['slug'], 'post' );
	if ( ! $post ) {
		return false;
	}
	$current = asfaltbama_importer_nhash( $post->post_content );
	if ( asfaltbama_importer_nhash( $content ) === $current ) {
		return false;
	}
	$known   = (array) ( $item['prev_hashes'] ?? [] );
	$known[] = (string) get_post_meta( $post->ID, '_asfaltbama_content_nhash', true );
	if ( in_array( $current, $known, true ) ) {
		return true;
	}
	$log[] = sprintf( '⚠️ /%s/ در وردپرس ویرایش شده؛ برای حفظ ویرایش شما با نسخه‌ی جدید قالب جایگزین نشد (برای جایگزینی: ابزارها ← محتوای آسفالت با ما)', $item['slug'] );
	return false;
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

	if ( ! empty( $item['robots'] ) ) {
		update_post_meta( $post_id, 'rank_math_robots', array_map( 'sanitize_key', (array) $item['robots'] ) );
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
		$content = asfaltbama_importer_read( $item['file'] );
		$id      = asfaltbama_importer_upsert(
			'page',
			$item['slug'],
			[
				'post_title'   => $item['title'],
				'post_content' => $content,
				'post_excerpt' => $item['excerpt'] ?? '',
				'post_status'  => 'publish',
			],
			$log,
			$update_existing || asfaltbama_importer_may_refresh( $item, $content )
		);
		if ( $id && ! empty( $item['managed'] ) ) {
			update_post_meta( $id, '_asfaltbama_content_hash', md5( get_post_field( 'post_content', $id ) ) );
		}
		if ( $id ) {
			asfaltbama_importer_seo( $id, $item );
		}
	}

	// 4. Posts.
	foreach ( $manifest['posts'] as $item ) {
		$category = get_category_by_slug( $item['category'] );
		if ( ! $category && ! empty( $item['category_name'] ) ) {
			$term = wp_insert_term( $item['category_name'], 'category', [ 'slug' => $item['category'] ] );
			if ( ! is_wp_error( $term ) ) {
				$category = get_term( $term['term_id'], 'category' );
				$log[]    = '✅ دسته‌بندی «' . $item['category_name'] . '» ساخته شد';
			}
		}
		$content  = asfaltbama_importer_read( $item['file'] );
		$exists   = (bool) asfaltbama_importer_find( $item['slug'], 'post' );
		$refresh  = $update_existing || ! $exists || asfaltbama_importer_may_refresh_post( $item, $content, $log );
		if ( $exists && ! $refresh ) {
			continue;
		}
		$id       = asfaltbama_importer_upsert(
			'post',
			$item['slug'],
			[
				'post_title'    => $item['title'],
				'post_content'  => $content,
				'post_excerpt'  => $item['excerpt'],
				'post_status'   => 'publish',
				'post_author'   => get_current_user_id(),
				'post_category' => $category ? [ $category->term_id ] : [],
			],
			$log,
			true
		);
		if ( $id ) {
			update_post_meta( $id, '_asfaltbama_content_nhash', asfaltbama_importer_nhash( get_post_field( 'post_content', $id ) ) );
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
 * Upload project photos from content/images to the media library (once
 * per file) and set them as featured images of the listed posts. A post's
 * featured image is only set when it has none or when it is one of
 * replace_featured_ids (e.g. the logo used as a placeholder) or was imported
 * from a file in replace_featured_sources (a branded cover).
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_images( $manifest ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$log     = [];
	$replace = array_map( 'intval', (array) ( $manifest['replace_featured_ids'] ?? [] ) );
	// Placeholders imported by this theme (e.g. branded covers) that a real
	// project photo may replace, by file name.
	$replace_sources = (array) ( $manifest['replace_featured_sources'] ?? [] );

	foreach ( (array) ( $manifest['images'] ?? [] ) as $image ) {
		$source = basename( $image['file'] );

		$existing = get_posts(
			[
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_asfaltbama_source', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $source, // phpcs:ignore WordPress.DB.SlowDBQuery
			]
		);

		if ( $existing ) {
			$attachment_id = (int) $existing[0];
		} else {
			$contents = asfaltbama_importer_read( $image['file'] );
			if ( '' === $contents ) {
				$log[] = '⚠️ فایل ' . $source . ' پیدا نشد';
				continue;
			}
			$upload = wp_upload_bits( $source, null, $contents );
			if ( ! empty( $upload['error'] ) ) {
				$log[] = '❌ ' . $source . ': ' . $upload['error'];
				continue;
			}
			$type          = wp_check_filetype( $upload['file'] );
			$attachment_id = wp_insert_attachment(
				[
					'post_title'     => $image['title'],
					'post_mime_type' => $type['type'],
					'post_status'    => 'inherit',
				],
				$upload['file']
			);
			if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
				$log[] = '❌ ' . $source . ': ثبت در کتابخانه‌ی رسانه ناموفق بود';
				continue;
			}
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
			update_post_meta( $attachment_id, '_asfaltbama_source', $source );
			$log[] = '✅ فایل «' . $image['title'] . '» به کتابخانه‌ی رسانه اضافه شد';
		}

		if ( '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $image['alt'] ) ) );
		}

		foreach ( (array) ( $image['featured_for'] ?? [] ) as $slug ) {
			$post = '__front__' === $slug ? get_post( (int) get_option( 'page_on_front' ) ) : asfaltbama_importer_find( $slug, 'post' );
			if ( ! $post ) {
				$post = asfaltbama_importer_find( $slug, 'page' );
			}
			if ( ! $post ) {
				$log[] = '⚠️ مقاله‌ی /' . $slug . '/ پیدا نشد';
				continue;
			}
			$current = (int) get_post_thumbnail_id( $post );
			$replaceable = in_array( $current, $replace, true )
				|| in_array( get_post_meta( $current, '_asfaltbama_source', true ), $replace_sources, true );
			if ( $current === $attachment_id ) {
				continue;
			}
			if ( $current && ! $replaceable ) {
				$log[] = '✅ /' . $slug . '/ تصویر شاخص خودش را دارد؛ دست نخورد';
				continue;
			}
			set_post_thumbnail( $post, $attachment_id );
			$log[] = '✅ تصویر شاخص /' . $slug . '/ تنظیم شد';
		}

		// Default share image for pages without their own (categories etc.),
		// only when none is set in Rank Math > Titles & Meta > Global.
		if ( ! empty( $image['default_og'] ) ) {
			$titles = get_option( 'rank-math-options-titles', [] );
			if ( is_array( $titles ) && empty( $titles['open_graph_image'] ) ) {
				$titles['open_graph_image']    = wp_get_attachment_url( $attachment_id );
				$titles['open_graph_image_id'] = $attachment_id;
				update_option( 'rank-math-options-titles', $titles );
				$log[] = '✅ تصویر پیش‌فرض اشتراک‌گذاری در Rank Math تنظیم شد';
			}
		}
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
		if ( ! empty( $seo['post_title'] ) && $seo['post_title'] !== $page->post_title ) {
			wp_update_post(
				[
					'ID'         => $page->ID,
					'post_title' => $seo['post_title'],
				]
			);
		}
		asfaltbama_importer_seo( $page->ID, $seo );
		$log[] = ! empty( $seo['robots'] ) && in_array( 'noindex', (array) $seo['robots'], true )
			? '✅ ' . $name . ' تا تکمیل محتوا از نتایج گوگل کنار گذاشته شد (noindex)'
			: '✅ عنوان و توضیحات ' . $name . ' به‌روز شد';
	}

	return $log;
}

/**
 * Fill empty site settings (site title and tagline) and describe the
 * article categories: term description plus Rank Math title and
 * description. Only empty values are filled; anything set in WordPress
 * stays as it is.
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_site( $manifest ) {
	$log = [];

	$labels = [
		'blogname'        => 'عنوان سایت',
		'blogdescription' => 'معرفی کوتاه سایت',
	];
	foreach ( $labels as $option => $label ) {
		$value = (string) ( $manifest['site'][ $option ] ?? '' );
		if ( '' === $value ) {
			continue;
		}
		if ( '' !== trim( (string) get_option( $option ) ) ) {
			$log[] = '✅ ' . $label . ' از قبل تنظیم شده است';
			continue;
		}
		update_option( $option, sanitize_text_field( $value ) );
		$log[] = '✅ ' . $label . ' تنظیم شد: ' . $value;
	}

	$meta = [
		'seo_title'       => 'rank_math_title',
		'seo_description' => 'rank_math_description',
	];
	foreach ( (array) ( $manifest['categories'] ?? [] ) as $slug => $item ) {
		$term = get_category_by_slug( $slug );
		if ( ! $term ) {
			$log[] = '⚠️ دسته‌بندی ' . $slug . ' پیدا نشد';
			continue;
		}
		if ( ! empty( $item['description'] ) && '' === trim( $term->description ) ) {
			wp_update_term( $term->term_id, 'category', [ 'description' => $item['description'] ] );
		}
		foreach ( $meta as $key => $meta_key ) {
			if ( ! empty( $item[ $key ] ) && '' === trim( (string) get_term_meta( $term->term_id, $meta_key, true ) ) ) {
				update_term_meta( $term->term_id, $meta_key, wp_slash( $item[ $key ] ) );
			}
		}
		$log[] = '✅ عنوان و توضیحات دسته‌بندی «' . $term->name . '» تنظیم شد';
	}

	return $log;
}

/**
 * Attachment imported from content/images, by file name.
 *
 * @param string $source File name.
 *
 * @return int Attachment ID, or 0.
 */
function asfaltbama_importer_media_id( $source ) {
	$ids = get_posts(
		[
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_asfaltbama_source', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $source, // phpcs:ignore WordPress.DB.SlowDBQuery
		]
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Apply the manifest's text replacements to every string in a value.
 *
 * @param mixed $value        Value.
 * @param array $replacements Search => replace.
 *
 * @return mixed
 */
function asfaltbama_importer_replace_deep( $value, $replacements ) {
	if ( is_string( $value ) ) {
		return strtr( $value, $replacements );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = asfaltbama_importer_replace_deep( $item, $replacements );
		}
	}
	return $value;
}

/**
 * Walk Elementor elements and apply one page's edits.
 *
 * @param array $elements Elementor elements.
 * @param array $edit     Page edits from the manifest.
 * @param array $media    Resolved media: image_swaps (old ID => [id, url]) and
 *                        background_videos (old file name => [video url, poster id, poster url]).
 * @param int   $changes  Number of changes, by reference.
 *
 * @return array
 */
function asfaltbama_importer_edit_elements( $elements, $edit, $media, &$changes ) {
	foreach ( $elements as $i => $element ) {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];

		// Counters, matched by their title.
		if ( 'counter' === ( $element['widgetType'] ?? '' ) ) {
			foreach ( (array) ( $edit['counters'] ?? [] ) as $counter ) {
				if ( isset( $settings['title'] ) && trim( $settings['title'] ) === $counter['title'] ) {
					$settings = array_merge( $settings, $counter['set'] );
					++$changes;
				}
			}
		}

		// Background videos: lighter file, no playback on phones, poster instead.
		if ( ! empty( $settings['background_video_link'] ) ) {
			$file = basename( wp_parse_url( $settings['background_video_link'], PHP_URL_PATH ) );
			if ( isset( $media['background_videos'][ $file ] ) ) {
				list( $video_url, $poster_id, $poster_url ) = $media['background_videos'][ $file ];
				$settings['background_video_link']     = $video_url;
				$settings['background_play_on_mobile'] = '';
				$settings['background_video_fallback'] = [
					'url' => $poster_url,
					'id'  => $poster_id,
				];
				++$changes;
			}
		}

		// Swapped images, wherever an {id, url} pair points at them.
		$settings = asfaltbama_importer_swap_images( $settings, $media['image_swaps'], $changes );

		// Edits to single widgets, by Elementor element ID: settings to set,
		// and "image" (a file from content/images) for image widgets.
		$widget_edit = $edit['widgets'][ $element['id'] ?? '' ] ?? null;
		if ( is_array( $widget_edit ) ) {
			foreach ( (array) ( $widget_edit['settings'] ?? [] ) as $key => $value ) {
				$settings[ $key ] = $value;
			}
			if ( ! empty( $widget_edit['image'] ) ) {
				$new_id = asfaltbama_importer_media_id( $widget_edit['image'] );
				if ( $new_id ) {
					$settings['image'] = [
						'id'  => $new_id,
						'url' => wp_get_attachment_url( $new_id ),
						'alt' => (string) get_post_meta( $new_id, '_wp_attachment_image_alt', true ),
					];
				}
			}
			++$changes;
		}

		if ( $settings ) {
			$element['settings'] = $settings;
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = asfaltbama_importer_edit_elements( $element['elements'], $edit, $media, $changes );
		}
		$elements[ $i ] = $element;
	}
	return $elements;
}

/**
 * Replace {id, url} image values whose ID is in $swaps.
 *
 * @param mixed $value   Setting value.
 * @param array $swaps   Old ID => [new ID, new URL].
 * @param int   $changes Number of changes, by reference.
 *
 * @return mixed
 */
function asfaltbama_importer_swap_images( $value, $swaps, &$changes ) {
	if ( ! is_array( $value ) || ! $swaps ) {
		return $value;
	}
	if ( isset( $value['id'], $value['url'] ) && isset( $swaps[ (int) $value['id'] ] ) ) {
		list( $value['id'], $value['url'] ) = $swaps[ (int) $value['id'] ];
		++$changes;
		return $value;
	}
	foreach ( $value as $key => $item ) {
		$value[ $key ] = asfaltbama_importer_swap_images( $item, $swaps, $changes );
	}
	return $value;
}

/**
 * Edit Elementor pages: counters, text (e.g. years of experience), swapped
 * images and lighter background videos that do not play on phones.
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_elementor( $manifest ) {
	$log          = [];
	$replacements = (array) ( $manifest['text_replacements'] ?? [] );

	foreach ( (array) ( $manifest['elementor_edits'] ?? [] ) as $edit ) {
		$slug = $edit['page'];
		$page = '__front__' === $slug ? get_post( (int) get_option( 'page_on_front' ) ) : asfaltbama_importer_find( $slug, 'page' );
		$name = '__front__' === $slug ? 'صفحه‌ی اصلی' : '/' . $slug . '/';
		if ( ! $page ) {
			$log[] = '⚠️ ' . $name . ' پیدا نشد';
			continue;
		}

		$media = [
			'image_swaps'       => [],
			'background_videos' => [],
		];
		foreach ( (array) ( $edit['image_swaps'] ?? [] ) as $old_id => $source ) {
			$new_id = asfaltbama_importer_media_id( $source );
			if ( $new_id ) {
				$media['image_swaps'][ (int) $old_id ] = [ $new_id, wp_get_attachment_url( $new_id ) ];
			}
		}
		foreach ( (array) ( $edit['background_videos'] ?? [] ) as $old_file => $files ) {
			$video_id  = asfaltbama_importer_media_id( $files['video'] );
			$poster_id = asfaltbama_importer_media_id( $files['poster'] );
			if ( $video_id && $poster_id ) {
				$media['background_videos'][ $old_file ] = [ wp_get_attachment_url( $video_id ), $poster_id, wp_get_attachment_url( $poster_id ) ];
			}
		}

		$changes = 0;
		$raw     = get_post_meta( $page->ID, '_elementor_data', true );
		$data    = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		if ( is_array( $data ) ) {
			$before = wp_json_encode( $data );
			$data   = asfaltbama_importer_edit_elements( $data, $edit, $media, $changes );
			$data   = asfaltbama_importer_replace_deep( $data, $replacements );
			if ( wp_json_encode( $data ) !== $before ) {
				update_post_meta( $page->ID, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
				delete_post_meta( $page->ID, '_elementor_element_cache' );
				delete_post_meta( $page->ID, '_elementor_css' );
				++$changes;
			}
		}

		foreach ( [ 'rank_math_title', 'rank_math_description' ] as $meta_key ) {
			$value = (string) get_post_meta( $page->ID, $meta_key, true );
			$new   = strtr( $value, $replacements );
			if ( $new !== $value ) {
				update_post_meta( $page->ID, $meta_key, wp_slash( $new ) );
				++$changes;
			}
		}
		$content = strtr( $page->post_content, $replacements );
		if ( $content !== $page->post_content ) {
			wp_update_post(
				[
					'ID'           => $page->ID,
					'post_content' => $content,
				]
			);
			++$changes;
		}

		$log[] = $changes ? '✅ ' . $name . ' به‌روز شد' : '✅ ' . $name . ' نیازی به تغییر نداشت';
	}

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	return $log;
}

/**
 * Rewrite Rank Math title, description and focus keywords of the
 * manifest's articles. Overwrites on purpose: the focus keywords used to be
 * written without the zero-width non-joiner the titles use (عایق کاری vs
 * عایق‌کاری), so Rank Math reported them missing from the titles.
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_post_seo( $manifest ) {
	$log   = [];
	$count = 0;
	foreach ( (array) ( $manifest['posts'] ?? [] ) as $item ) {
		$post = asfaltbama_importer_find( $item['slug'], 'post' );
		if ( ! $post ) {
			continue;
		}
		asfaltbama_importer_seo( $post->ID, $item );
		++$count;
	}
	$log[] = '✅ کلمه‌ی کلیدی، عنوان و توضیحات سئوی ' . $count . ' مقاله به‌روز شد';

	return $log;
}

/**
 * Clean up leftover tags and tag the articles.
 *
 * Only empty tags are deleted: demo tags from the theme import, a tag
 * holding pasted admin text and a misspelt one. Tag names with invisible
 * word joiners (U+2060) are cleaned, or deleted when a clean twin exists.
 * Tags are then added to the manifest's articles (existing tags are kept).
 *
 * @param array $manifest Manifest.
 *
 * @return string[] Log lines.
 */
function asfaltbama_importer_tags( $manifest ) {
	$log     = [];
	$cleanup = (array) ( $manifest['tags_cleanup'] ?? [] );
	$deleted = 0;
	$renamed = 0;

	$terms = get_terms(
		[
			'taxonomy'   => 'post_tag',
			'hide_empty' => false,
		]
	);
	$names = [];
	foreach ( is_array( $terms ) ? $terms : [] as $term ) {
		$names[ $term->name ] = $term->term_id;
	}

	foreach ( is_array( $terms ) ? $terms : [] as $term ) {
		if ( $term->count > 0 ) {
			continue;
		}
		$delete = in_array( $term->slug, (array) ( $cleanup['delete_slugs'] ?? [] ), true );
		foreach ( (array) ( $cleanup['delete_names_containing'] ?? [] ) as $needle ) {
			if ( false !== mb_strpos( $term->name, $needle ) ) {
				$delete = true;
			}
		}

		$clean = trim( str_replace( [ "\u{2060}", "\u{200B}", "\u{FEFF}" ], '', $term->name ), " \t\n\r\u{200C}" );
		if ( ! $delete && $clean !== $term->name ) {
			if ( isset( $names[ $clean ] ) ) {
				$delete = true;
			} else {
				wp_update_term(
					$term->term_id,
					'post_tag',
					[
						'name' => $clean,
						'slug' => sanitize_title( $clean ),
					]
				);
				$names[ $clean ] = $term->term_id;
				++$renamed;
			}
		}

		if ( $delete ) {
			wp_delete_term( $term->term_id, 'post_tag' );
			++$deleted;
		}
	}
	if ( $deleted || $renamed ) {
		$log[] = sprintf( '✅ برچسب‌های اضافه: %d حذف و %d اصلاح شد', $deleted, $renamed );
	}

	$tagged = 0;
	foreach ( (array) ( $manifest['post_tags'] ?? [] ) as $slug => $tags ) {
		$post = asfaltbama_importer_find( $slug, 'post' );
		if ( ! $post ) {
			continue;
		}
		wp_set_post_tags( $post->ID, array_values( (array) $tags ), true );
		++$tagged;
	}
	$log[] = '✅ به ' . $tagged . ' مقاله برچسب اضافه شد';

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

	if ( ! empty( $manifest['site_version'] ) && get_option( 'asfaltbama_site_version' ) !== $manifest['site_version'] ) {
		update_option( 'asfaltbama_site_version', $manifest['site_version'], false );
		$log = array_merge( $log, asfaltbama_importer_site( $manifest ) );
	}

	if ( ! empty( $manifest['post_seo_version'] ) && get_option( 'asfaltbama_post_seo_version' ) !== $manifest['post_seo_version'] ) {
		update_option( 'asfaltbama_post_seo_version', $manifest['post_seo_version'], false );
		$log = array_merge( $log, asfaltbama_importer_post_seo( $manifest ) );
	}

	if ( ! empty( $manifest['tags_version'] ) && get_option( 'asfaltbama_tags_version' ) !== $manifest['tags_version'] ) {
		update_option( 'asfaltbama_tags_version', $manifest['tags_version'], false );
		$log = array_merge( $log, asfaltbama_importer_tags( $manifest ) );
	}

	if ( ! empty( $manifest['media_alt_version'] ) && get_option( 'asfaltbama_media_alt_version' ) !== $manifest['media_alt_version'] ) {
		update_option( 'asfaltbama_media_alt_version', $manifest['media_alt_version'], false );
		$log = array_merge( $log, asfaltbama_importer_media_alt( $manifest ) );
	}

	if ( ! empty( $manifest['page_seo_version'] ) && get_option( 'asfaltbama_page_seo_version' ) !== $manifest['page_seo_version'] ) {
		update_option( 'asfaltbama_page_seo_version', $manifest['page_seo_version'], false );
		$log = array_merge( $log, asfaltbama_importer_page_seo( $manifest ) );
	}

	if ( ! empty( $manifest['images_version'] ) && get_option( 'asfaltbama_images_version' ) !== $manifest['images_version'] ) {
		update_option( 'asfaltbama_images_version', $manifest['images_version'], false );
		$log = array_merge( $log, asfaltbama_importer_images( $manifest ) );
	}

	// Prices shipped with the theme (the owner can change them afterwards
	// under Settings → قیمت روز; they are only rewritten when prices_version changes).
	if ( ! empty( $manifest['prices_version'] ) && ! empty( $manifest['prices'] ) && get_option( 'asfaltbama_prices_version' ) !== $manifest['prices_version'] ) {
		update_option( 'asfaltbama_prices_version', $manifest['prices_version'], false );
		$all = (array) get_option( 'asfaltbama_prices', [] );
		foreach ( (array) $manifest['prices'] as $group => $data ) {
			// Never replace prices the owner saved under Settings → قیمت روز.
			// Saves from before 1.43 carry no source: they are the owner's too.
			$prev = $all[ $group ] ?? [];
			if ( ( $prev['source'] ?? '' ) === 'owner' || ( ! isset( $prev['source'] ) && ! empty( $prev['prices'] ) ) ) {
				$log[] = 'ℹ️ قیمت روز «' . $group . '» را خودتان ثبت کرده‌اید؛ دست‌نخورده ماند';
				continue;
			}
			$all[ $group ] = [
				'source'  => 'theme',
				'prices'  => array_map( 'strval', (array) ( $data['prices'] ?? [] ) ),
				'note'    => (string) ( $data['note'] ?? '' ),
				'updated' => ! empty( $data['updated'] ) ? (int) strtotime( $data['updated'] ) : time(),
			];
			$log[] = '✅ قیمت روز «' . $group . '» ثبت شد';
		}
		update_option( 'asfaltbama_prices', $all );
	}

	// After the images step: it needs the imported media.
	if ( ! empty( $manifest['elementor_version'] ) && get_option( 'asfaltbama_elementor_version' ) !== $manifest['elementor_version'] ) {
		update_option( 'asfaltbama_elementor_version', $manifest['elementor_version'], false );
		$log = array_merge( $log, asfaltbama_importer_elementor( $manifest ) );
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
		$log = array_merge( asfaltbama_importer_run( $manifest ), asfaltbama_importer_site( $manifest ) );
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
