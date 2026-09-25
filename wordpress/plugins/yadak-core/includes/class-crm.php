<?php
/**
 * CRM: leads and sales pipeline, activity log (calls, notes, meetings,
 * follow-ups) for leads and customers, a follow-up inbox, customer 360°
 * summary on the user screen, and customer segments in the users list.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_CRM {

	const LEAD = 'yadak_lead';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::LEAD, array( __CLASS__, 'lead_boxes' ) );
		add_action( 'save_post_' . self::LEAD, array( __CLASS__, 'save_lead' ) );
		add_filter( 'manage_' . self::LEAD . '_posts_columns', array( __CLASS__, 'lead_columns' ) );
		add_action( 'manage_' . self::LEAD . '_posts_custom_column', array( __CLASS__, 'lead_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'lead_filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_leads' ) );

		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_yadak_activity_done', array( __CLASS__, 'handle_done' ) );
		add_action( 'admin_post_yadak_lead_stage', array( __CLASS__, 'handle_stage' ) );
		add_action( 'admin_post_yadak_lead_convert', array( __CLASS__, 'handle_convert' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'customer_360' ), 1 );
		add_action( 'edit_user_profile', array( __CLASS__, 'customer_360' ), 1 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_activity' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_activity' ) );

		add_action( 'restrict_manage_users', array( __CLASS__, 'user_filters' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'lead_assets' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'deal_from_order' ), 30, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'deal_from_order' ), 30, 2 );
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users' ) );
	}

	/* ---------- Definitions ---------- */

	public static function stages() {
		return array(
			'lead'        => __( 'سرنخ', 'yadak-core' ),
			'contacted'   => __( 'تماس گرفته شد', 'yadak-core' ),
			'interested'  => __( 'علاقه‌مند', 'yadak-core' ),
			'quotation'   => __( 'پیش‌فاکتور', 'yadak-core' ),
			'negotiation' => __( 'مذاکره', 'yadak-core' ),
			'won'         => __( 'سفارش ثبت شد', 'yadak-core' ),
			'paid'        => __( 'پرداخت شد', 'yadak-core' ),
			'shipped'     => __( 'ارسال شد', 'yadak-core' ),
			'lost'        => __( 'از دست رفت', 'yadak-core' ),
		);
	}

	public static function channels() {
		return array(
			'b2b' => __( 'عمده / همکار (B2B)', 'yadak-core' ),
			'b2c' => __( 'خرده (B2C)', 'yadak-core' ),
		);
	}

	public static function lead_assets() {
		$screen = get_current_screen();
		if ( $screen && self::LEAD === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}
	}

	/**
	 * Move a deal to a stage (only forward, never out of "lost") and log it.
	 */
	public static function set_deal_stage( $deal_id, $stage ) {
		$order = array_keys( self::stages() );
		$old   = self::lead_meta( $deal_id, 'stage' );
		if ( 'lost' === $old || array_search( $stage, $order, true ) <= array_search( $old, $order, true ) ) {
			return;
		}
		update_post_meta( $deal_id, '_yl_stage', $stage );
		/* translators: 1: old stage, 2: new stage */
		self::add_activity( 'lead', $deal_id, 'note', sprintf( __( 'تغییر خودکار مرحله: %1$s ← %2$s', 'yadak-core' ), self::stages()[ $old ] ?? $old, self::stages()[ $stage ] ), '', 0, true );
	}

	/**
	 * Paid → "پرداخت شد", completed → "ارسال شد" for the deal behind an order.
	 */
	public static function deal_from_order( $order_id, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );
		$deal  = $order ? (int) $order->get_meta( '_yadak_deal' ) : 0;
		if ( $deal ) {
			self::set_deal_stage( $deal, 'completed' === $order->get_status() ? 'shipped' : 'paid' );
		}
	}

	public static function activity_types() {
		return array(
			'call'     => __( 'تماس', 'yadak-core' ),
			'note'     => __( 'یادداشت', 'yadak-core' ),
			'meeting'  => __( 'جلسه / بازدید', 'yadak-core' ),
			'sms'      => __( 'پیام', 'yadak-core' ),
			'followup' => __( 'پیگیری', 'yadak-core' ),
		);
	}

	public static function sources() {
		return array(
			''          => __( '—', 'yadak-core' ),
			'phone'     => __( 'تماس تلفنی', 'yadak-core' ),
			'instagram' => __( 'اینستاگرام', 'yadak-core' ),
			'website'   => __( 'سایت', 'yadak-core' ),
			'referral'  => __( 'معرفی', 'yadak-core' ),
			'visit'     => __( 'مراجعه / بازاریابی حضوری', 'yadak-core' ),
			'other'     => __( 'سایر', 'yadak-core' ),
		);
	}

	public static function register() {
		register_post_type(
			self::LEAD,
			array(
				'labels'          => array(
					'name'          => __( 'سرنخ‌ها و فرصت‌ها', 'yadak-core' ),
					'singular_name' => __( 'فرصت فروش', 'yadak-core' ),
					'add_new'       => __( 'فرصت جدید', 'yadak-core' ),
					'add_new_item'  => __( 'سرنخ / فرصت فروش جدید', 'yadak-core' ),
					'edit_item'     => __( 'ویرایش فرصت فروش', 'yadak-core' ),
					'all_items'     => __( 'سرنخ‌ها و فرصت‌ها', 'yadak-core' ),
					'search_items'  => __( 'جستجو', 'yadak-core' ),
					'not_found'     => __( 'موردی نیست.', 'yadak-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Yadak_Settings::MENU,
				'supports'        => array( 'title' ),
				'capability_type' => 'shop_order',
				'map_meta_cap'    => true,
			)
		);
	}

	/* ---------- Activities ---------- */

	/**
	 * @param string $object_type "user" or "lead".
	 * @param int    $object_id   ID.
	 * @param string $type        Activity type.
	 * @param string $note        Text.
	 * @param string $due_date    Follow-up date Y-m-d, or ''.
	 * @param int    $assigned_to Staff user, 0 = creator.
	 * @param bool   $done        Already done (a log entry, not a task).
	 * @return int
	 */
	public static function add_activity( $object_type, $object_id, $type, $note, $due_date = '', $assigned_to = 0, $done = false ) {
		global $wpdb;
		if ( ! $object_id || '' === trim( $note ) ) {
			return 0;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Yadak_Install::table( 'activity' ),
			array(
				'object_type' => $object_type,
				'object_id'   => (int) $object_id,
				'type'        => array_key_exists( $type, self::activity_types() ) ? $type : 'note',
				'note'        => $note,
				'due_date'    => $due_date ? $due_date : null,
				'done'        => ( $done || ! $due_date ) ? 1 : 0,
				'assigned_to' => $assigned_to ? (int) $assigned_to : get_current_user_id(),
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function activities( $object_type, $object_id ) {
		global $wpdb;
		$table = Yadak_Install::table( 'activity' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT 100", $object_type, $object_id ) );
	}

	private static function done_url( $activity_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=yadak_activity_done&id=' . (int) $activity_id ), 'yadak_activity_done_' . (int) $activity_id );
	}

	/**
	 * Activity list + "add activity" fields (posted with the surrounding form).
	 */
	public static function render_activities( $object_type, $object_id ) {
		$types = self::activity_types();
		?>
		<div class="yadak-activity">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'ثبت فعالیت جدید', 'yadak-core' ); ?></th>
					<td>
						<select name="yadak_act_type">
							<?php foreach ( $types as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<textarea name="yadak_act_note" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'چه گفته شد؟ چه قرار شد؟', 'yadak-core' ); ?>"></textarea>
						<label><?php esc_html_e( 'تاریخ پیگیری بعدی (اختیاری):', 'yadak-core' ); ?> <input type="text" name="yadak_act_due" dir="ltr" size="12" placeholder="<?php echo esc_attr( yadak_date_input_value( yadak_today( '+2 days' ) ) ); ?>"></label>
					</td>
				</tr>
			</table>
			<?php
			$rows = $object_id ? self::activities( $object_type, $object_id ) : array();
			if ( $rows ) {
				echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'زمان', 'yadak-core' ) . '</th><th>' . esc_html__( 'نوع', 'yadak-core' ) . '</th><th>' . esc_html__( 'شرح', 'yadak-core' ) . '</th><th>' . esc_html__( 'پیگیری', 'yadak-core' ) . '</th><th>' . esc_html__( 'کارشناس', 'yadak-core' ) . '</th></tr></thead><tbody>';
				foreach ( $rows as $row ) {
					$by = get_userdata( (int) $row->created_by );
					echo '<tr><td>' . esc_html( yadak_show_date( $row->created_at, 'Y/m/d H:i', true ) ) . '</td>';
					echo '<td>' . esc_html( isset( $types[ $row->type ] ) ? $types[ $row->type ] : $row->type ) . '</td>';
					echo '<td>' . nl2br( esc_html( $row->note ) ) . '</td><td>';
					if ( $row->due_date ) {
						echo esc_html( yadak_show_date( $row->due_date ) );
						echo $row->done ? ' ✓' : ' — <a href="' . esc_url( self::done_url( $row->id ) ) . '">' . esc_html__( 'انجام شد', 'yadak-core' ) . '</a>';
					}
					echo '</td><td>' . esc_html( $by ? $by->display_name : __( 'سیستم', 'yadak-core' ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Save the "add activity" fields from a form.
	 */
	private static function save_posted_activity( $object_type, $object_id, $assigned_to = 0 ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify their form nonce first.
		if ( empty( $_POST['yadak_act_note'] ) ) {
			return;
		}
		self::add_activity(
			$object_type,
			$object_id,
			isset( $_POST['yadak_act_type'] ) ? sanitize_key( $_POST['yadak_act_type'] ) : 'note',
			sanitize_textarea_field( wp_unslash( $_POST['yadak_act_note'] ) ),
			yadak_parse_date_input( isset( $_POST['yadak_act_due'] ) ? sanitize_text_field( wp_unslash( $_POST['yadak_act_due'] ) ) : '' ),
			$assigned_to
		);
		// phpcs:enable
	}

	public static function handle_done() {
		global $wpdb;
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'yadak_activity_done_' . $id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		$wpdb->update( Yadak_Install::table( 'activity' ), array( 'done' => 1 ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=yadak-followups' ) );
		exit;
	}

	/* ---------- Leads ---------- */

	public static function lead_meta( $lead_id, $key ) {
		return (string) get_post_meta( $lead_id, '_yl_' . $key, true );
	}

	public static function lead_boxes() {
		add_meta_box( 'yadak-lead', __( 'اطلاعات سرنخ', 'yadak-core' ), array( __CLASS__, 'render_lead_box' ), self::LEAD, 'normal', 'high' );
		add_meta_box( 'yadak-lead-activity', __( 'فعالیت‌ها و پیگیری', 'yadak-core' ), array( __CLASS__, 'render_lead_activity' ), self::LEAD, 'normal', 'default' );
		add_meta_box( 'yadak-lead-actions', __( 'تبدیل', 'yadak-core' ), array( __CLASS__, 'render_lead_actions' ), self::LEAD, 'side', 'default' );
	}

	public static function render_lead_box( $post ) {
		wp_nonce_field( 'yadak_lead_save', 'yadak_lead_nonce' );
		$rep = (int) self::lead_meta( $post->ID, 'rep' );
		if ( ! $rep && 'auto-draft' === $post->post_status ) {
			$rep = get_current_user_id();
		}
		$select = static function ( $name, $options, $value ) {
			echo '<select name="' . esc_attr( $name ) . '">';
			foreach ( $options as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		};
		$reps = array( 0 => __( '—', 'yadak-core' ) );
		foreach ( Yadak_Customers::sales_reps() as $user ) {
			$reps[ $user->ID ] = $user->display_name;
		}
		?>
		<p class="description"><?php esc_html_e( 'عنوان بالا = نام شخص یا کسب‌وکار.', 'yadak-core' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'نوع فروش', 'yadak-core' ); ?></th><td><?php $select( 'yl[channel]', self::channels(), self::lead_meta( $post->ID, 'channel' ) ? self::lead_meta( $post->ID, 'channel' ) : 'b2b' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'مشتری موجود', 'yadak-core' ); ?></th><td>
				<?php $linked = get_userdata( (int) self::lead_meta( $post->ID, 'user' ) ); ?>
				<select name="yl[user]" class="wc-customer-search" style="width:320px" data-allow_clear="true" data-placeholder="<?php esc_attr_e( 'برای مشتری ثبت‌شده انتخاب کنید (اختیاری)', 'yadak-core' ); ?>">
					<?php if ( $linked ) : ?><option value="<?php echo esc_attr( $linked->ID ); ?>" selected><?php echo esc_html( Yadak_SMS::user_name( $linked->ID ) ); ?></option><?php endif; ?>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'موبایل', 'yadak-core' ); ?></th><td><input type="text" name="yl[mobile]" value="<?php echo esc_attr( self::lead_meta( $post->ID, 'mobile' ) ); ?>" dir="ltr"></td></tr>
			<tr><th><?php esc_html_e( 'نام فروشگاه / تعمیرگاه / شرکت', 'yadak-core' ); ?></th><td><input type="text" class="regular-text" name="yl[company]" value="<?php echo esc_attr( self::lead_meta( $post->ID, 'company' ) ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'شهر', 'yadak-core' ); ?></th><td><input type="text" name="yl[city]" value="<?php echo esc_attr( self::lead_meta( $post->ID, 'city' ) ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'نوع مشتری', 'yadak-core' ); ?></th><td><?php $select( 'yl[type]', yadak_customer_groups(), self::lead_meta( $post->ID, 'type' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'مرحله', 'yadak-core' ); ?></th><td><?php $select( 'yl[stage]', self::stages(), self::lead_meta( $post->ID, 'stage' ) ? self::lead_meta( $post->ID, 'stage' ) : 'lead' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'منبع', 'yadak-core' ); ?></th><td><?php $select( 'yl[source]', self::sources(), self::lead_meta( $post->ID, 'source' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'کارشناس فروش', 'yadak-core' ); ?></th><td><?php $select( 'yl[rep]', $reps, $rep ); ?></td></tr>
			<tr><th><?php esc_html_e( 'ارزش تخمینی خرید ماهانه (تومان)', 'yadak-core' ); ?></th><td><input type="text" name="yl[value]" value="<?php echo esc_attr( self::lead_meta( $post->ID, 'value' ) ); ?>" dir="ltr"></td></tr>
			<tr><th><?php esc_html_e( 'پیگیری بعدی', 'yadak-core' ); ?></th><td><input type="text" name="yl[next]" value="<?php echo esc_attr( yadak_date_input_value( self::lead_meta( $post->ID, 'next' ) ) ); ?>" dir="ltr" placeholder="1405/07/10"></td></tr>
		</table>
		<?php
	}

	public static function render_lead_activity( $post ) {
		self::render_activities( 'lead', $post->ID );
	}

	public static function render_lead_actions( $post ) {
		$user_id = (int) self::lead_meta( $post->ID, 'user' );
		if ( $user_id && get_userdata( $user_id ) ) {
			echo '<p><a class="button" href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html__( 'پرونده مشتری', 'yadak-core' ) . '</a></p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . Yadak_Quotes::POST_TYPE . '&yq_customer=' . $user_id . '&yq_deal=' . $post->ID ) ) . '">' . esc_html__( 'پیش‌فاکتور برای این فرصت', 'yadak-core' ) . '</a></p>';
			$quote = (int) self::lead_meta( $post->ID, 'quote' );
			$order = wc_get_order( (int) self::lead_meta( $post->ID, 'order' ) );
			if ( $quote ) {
				/* translators: 1: quote number, 2: status */
				echo '<p><a href="' . esc_url( (string) get_edit_post_link( $quote ) ) . '">' . esc_html( sprintf( __( 'پیش‌فاکتور #%1$d — %2$s', 'yadak-core' ), $quote, Yadak_Quotes::statuses()[ Yadak_Quotes::status( $quote ) ] ) ) . '</a></p>';
			}
			if ( $order ) {
				/* translators: 1: order number, 2: status */
				echo '<p><a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'سفارش #%1$s — %2$s', 'yadak-core' ), $order->get_order_number(), wc_get_order_status_name( $order->get_status() ) ) ) . '</a>';
				if ( $order->get_meta( '_yadak_tracking' ) ) {
					echo '<br>' . esc_html__( 'کد رهگیری:', 'yadak-core' ) . ' <span dir="ltr">' . esc_html( $order->get_meta( '_yadak_tracking' ) ) . '</span>';
				}
				echo '</p>';
			}
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'ابتدا ذخیره کنید.', 'yadak-core' ) . '</p>';
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=yadak_lead_convert&lead=' . $post->ID ), 'yadak_lead_convert_' . $post->ID );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'تبدیل به مشتری', 'yadak-core' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'حساب کاربری با شماره موبایل ساخته (یا پیدا) می‌شود و سابقه فعالیت‌ها منتقل می‌شود. سپس می‌توانید برایش پیش‌فاکتور صادر کنید.', 'yadak-core' ) . '</p>';
	}

	public static function save_lead( $post_id ) {
		if ( ! isset( $_POST['yadak_lead_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['yadak_lead_nonce'] ), 'yadak_lead_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['yl'] ) && is_array( $_POST['yl'] ) ? wp_unslash( $_POST['yl'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
		$old_stage = self::lead_meta( $post_id, 'stage' );
		foreach ( array( 'channel', 'user', 'mobile', 'company', 'city', 'type', 'stage', 'source', 'rep', 'value', 'next' ) as $key ) {
			if ( ! isset( $in[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( yadak_normalize_digits( $in[ $key ] ) );
			if ( 'next' === $key ) {
				$value = yadak_parse_date_input( $value );
			} elseif ( 'mobile' === $key ) {
				$value = Yadak_Checkout::normalize_mobile( $value ) ? Yadak_Checkout::normalize_mobile( $value ) : $value;
			} elseif ( 'user' === $key ) {
				$value = (string) absint( $value );
			} elseif ( 'channel' === $key && ! array_key_exists( $value, self::channels() ) ) {
				$value = 'b2b';
			} elseif ( 'stage' === $key && ! array_key_exists( $value, self::stages() ) ) {
				$value = 'lead';
			}
			update_post_meta( $post_id, '_yl_' . $key, $value );
		}
		$new_stage = self::lead_meta( $post_id, 'stage' );
		if ( $old_stage && $old_stage !== $new_stage ) {
			/* translators: 1: old stage, 2: new stage */
			self::add_activity( 'lead', $post_id, 'note', sprintf( __( 'تغییر مرحله: %1$s ← %2$s', 'yadak-core' ), self::stages()[ $old_stage ] ?? $old_stage, self::stages()[ $new_stage ] ?? $new_stage ), '', 0, true );
		}
		self::save_posted_activity( 'lead', $post_id, (int) self::lead_meta( $post_id, 'rep' ) );
	}

	public static function lead_columns( $columns ) {
		return array(
			'cb'        => $columns['cb'],
			'title'     => __( 'نام', 'yadak-core' ),
			'yl_mobile' => __( 'موبایل', 'yadak-core' ),
			'yl_stage'  => __( 'مرحله', 'yadak-core' ),
			'yl_rep'    => __( 'کارشناس', 'yadak-core' ),
			'yl_next'   => __( 'پیگیری بعدی', 'yadak-core' ),
			'date'      => $columns['date'],
		);
	}

	public static function lead_column( $column, $post_id ) {
		switch ( $column ) {
			case 'yl_mobile':
				echo '<span dir="ltr">' . esc_html( self::lead_meta( $post_id, 'mobile' ) ) . '</span>';
				break;
			case 'yl_stage':
				$stage = self::lead_meta( $post_id, 'stage' );
				echo esc_html( self::stages()[ $stage ] ?? '' );
				break;
			case 'yl_rep':
				$rep = get_userdata( (int) self::lead_meta( $post_id, 'rep' ) );
				echo esc_html( $rep ? $rep->display_name : '—' );
				break;
			case 'yl_next':
				$next = self::lead_meta( $post_id, 'next' );
				if ( $next ) {
					$late = $next < yadak_today() && ! in_array( self::lead_meta( $post_id, 'stage' ), array( 'won', 'paid', 'shipped', 'lost' ), true );
					echo '<span style="' . ( $late ? 'color:#b32d2e;font-weight:700' : '' ) . '">' . esc_html( yadak_show_date( $next ) ) . '</span>';
				}
				break;
		}
	}

	public static function lead_filters( $post_type ) {
		if ( self::LEAD !== $post_type ) {
			return;
		}
		$current = isset( $_GET['yl_stage'] ) ? sanitize_key( $_GET['yl_stage'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="yl_stage"><option value="">' . esc_html__( 'همه مراحل', 'yadak-core' ) . '</option>';
		foreach ( self::stages() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		$mine = ! empty( $_GET['yl_mine'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<label style="margin:0 6px"><input type="checkbox" name="yl_mine" value="1" ' . checked( $mine, true, false ) . '> ' . esc_html__( 'فقط سرنخ‌های من', 'yadak-core' ) . '</label>';
	}

	public static function filter_leads( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::LEAD !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$meta = array();
		if ( ! empty( $_GET['yl_stage'] ) ) {
			$meta[] = array(
				'key'   => '_yl_stage',
				'value' => sanitize_key( $_GET['yl_stage'] ),
			);
		}
		if ( ! empty( $_GET['yl_mine'] ) ) {
			$meta[] = array(
				'key'   => '_yl_rep',
				'value' => get_current_user_id(),
			);
		}
		// phpcs:enable
		if ( $meta ) {
			$query->set( 'meta_query', $meta );
		}
	}

	public static function handle_stage() {
		$lead  = isset( $_POST['lead'] ) ? absint( $_POST['lead'] ) : 0;
		check_admin_referer( 'yadak_lead_stage_' . $lead );
		if ( ! current_user_can( 'edit_post', $lead ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		$stage = isset( $_POST['stage'] ) ? sanitize_key( $_POST['stage'] ) : '';
		$old   = self::lead_meta( $lead, 'stage' );
		if ( array_key_exists( $stage, self::stages() ) && $stage !== $old ) {
			update_post_meta( $lead, '_yl_stage', $stage );
			/* translators: 1: old stage, 2: new stage */
			self::add_activity( 'lead', $lead, 'note', sprintf( __( 'تغییر مرحله: %1$s ← %2$s', 'yadak-core' ), self::stages()[ $old ] ?? $old, self::stages()[ $stage ] ), '', 0, true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=yadak-pipeline' ) );
		exit;
	}

	public static function handle_convert() {
		global $wpdb;
		$lead = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0;
		check_admin_referer( 'yadak_lead_convert_' . $lead );
		if ( ! current_user_can( 'edit_post', $lead ) || ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'yadak-core' ) );
		}
		$mobile = self::lead_meta( $lead, 'mobile' );
		$users  = get_userdata( (int) self::lead_meta( $lead, 'user' ) ) ? array( (int) self::lead_meta( $lead, 'user' ) ) : ( $mobile ? get_users(
			array(
				'meta_key'   => 'billing_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $mobile, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			)
		) : array() );
		$user_id = $users ? (int) $users[0] : 0;

		if ( ! $user_id ) {
			$login   = $mobile ? $mobile : 'lead' . $lead;
			$user_id = wp_insert_user(
				array(
					'user_login'   => username_exists( $login ) ? $login . '-' . $lead : $login,
					'user_pass'    => wp_generate_password( 20 ),
					'first_name'   => get_the_title( $lead ),
					'display_name' => get_the_title( $lead ),
					'role'         => 'customer',
				)
			);
			if ( is_wp_error( $user_id ) ) {
				wp_die( esc_html( $user_id->get_error_message() ) );
			}
			update_user_meta( $user_id, 'billing_phone', $mobile );
			update_user_meta( $user_id, 'billing_first_name', get_the_title( $lead ) );
			update_user_meta( $user_id, 'billing_city', self::lead_meta( $lead, 'city' ) );
			update_user_meta( $user_id, 'billing_country', 'IR' );
		}
		$type = self::lead_meta( $lead, 'type' );
		update_user_meta( $user_id, 'yadak_customer_group', array_key_exists( $type, yadak_customer_groups() ) ? $type : 'retail' );
		update_user_meta( $user_id, 'yadak_company', self::lead_meta( $lead, 'company' ) );
		update_user_meta( $user_id, 'yadak_sales_rep', (int) self::lead_meta( $lead, 'rep' ) );
		update_post_meta( $lead, '_yl_user', $user_id );

		// Move the history to the customer file.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Yadak_Install::table( 'activity' ),
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
			),
			array(
				'object_type' => 'lead',
				'object_id'   => $lead,
			)
		);
		/* translators: %s: lead title */
		self::add_activity( 'user', $user_id, 'note', sprintf( __( 'از سرنخ «%s» به مشتری تبدیل شد.', 'yadak-core' ), get_the_title( $lead ) ), '', 0, true );
		wp_safe_redirect( get_edit_user_link( $user_id ) );
		exit;
	}

	/* ---------- Pages ---------- */

	public static function menu() {
		add_submenu_page( Yadak_Settings::MENU, __( 'پیگیری‌های من', 'yadak-core' ), self::menu_label(), 'edit_shop_orders', 'yadak-followups', array( __CLASS__, 'render_followups' ), 1 );
		add_submenu_page( Yadak_Settings::MENU, __( 'قیف فروش (Deal Pipeline)', 'yadak-core' ), __( 'قیف فروش', 'yadak-core' ), 'edit_shop_orders', 'yadak-pipeline', array( __CLASS__, 'render_pipeline' ), 2 );
	}

	private static function menu_label() {
		$count = count( self::due_items( get_current_user_id(), yadak_today() ) );
		return __( 'پیگیری‌های من', 'yadak-core' ) . ( $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '' );
	}

	/**
	 * Open follow-ups (activities and leads) due by a date.
	 *
	 * @param int    $user_id Staff (0 = everyone).
	 * @param string $until   Y-m-d.
	 * @return array<int,array{date:string,title:string,url:string,note:string,done_url:string,who:int}>
	 */
	public static function due_items( $user_id, $until ) {
		global $wpdb;
		$table = Yadak_Install::table( 'activity' );
		$sql   = "SELECT * FROM {$table} WHERE done = 0 AND due_date IS NOT NULL AND due_date <= %s";
		$args  = array( $until );
		if ( $user_id ) {
			$sql   .= ' AND assigned_to = %d';
			$args[] = $user_id;
		}
		$items = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY due_date ASC LIMIT 300', $args ) ) as $row ) {
			if ( 'lead' === $row->object_type ) {
				$title = get_the_title( (int) $row->object_id );
				$url   = get_edit_post_link( (int) $row->object_id, 'url' );
			} else {
				$title = Yadak_SMS::user_name( (int) $row->object_id );
				$url   = get_edit_user_link( (int) $row->object_id ) . '#yadak-360';
			}
			$items[] = array(
				'date'     => $row->due_date,
				'title'    => $title,
				'url'      => (string) $url,
				'note'     => $row->note,
				'done_url' => self::done_url( $row->id ),
				'who'      => (int) $row->assigned_to,
			);
		}
		$lead_query = array(
			'post_type'      => self::LEAD,
			'posts_per_page' => 200,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_yl_next',
					'value'   => $until,
					'compare' => '<=',
					'type'    => 'DATE',
				),
				array(
					'key'     => '_yl_next',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => '_yl_stage',
					'value'   => array( 'won', 'paid', 'shipped', 'lost' ),
					'compare' => 'NOT IN',
				),
			),
		);
		if ( $user_id ) {
			$lead_query['meta_query'][] = array(
				'key'   => '_yl_rep',
				'value' => $user_id,
			);
		}
		foreach ( get_posts( $lead_query ) as $lead ) {
			$items[] = array(
				'date'     => self::lead_meta( $lead->ID, 'next' ),
				/* translators: %s: stage */
				'title'    => $lead->post_title . ' — ' . sprintf( __( 'سرنخ (%s)', 'yadak-core' ), self::stages()[ self::lead_meta( $lead->ID, 'stage' ) ] ?? '' ),
				'url'      => (string) get_edit_post_link( $lead->ID, 'url' ),
				'note'     => self::lead_meta( $lead->ID, 'company' ),
				'done_url' => '',
				'who'      => (int) self::lead_meta( $lead->ID, 'rep' ),
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);
		return $items;
	}

	public static function render_followups() {
		$all   = ! empty( $_GET['all'] ) && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$items = self::due_items( $all ? 0 : get_current_user_id(), yadak_today( '+7 days' ) );
		$today = yadak_today();
		echo '<div class="wrap"><h1>' . esc_html__( 'پیگیری‌ها', 'yadak-core' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=yadak-followups' ) ) . '">' . esc_html__( 'مال من', 'yadak-core' ) . '</a> | <a href="' . esc_url( admin_url( 'admin.php?page=yadak-followups&all=1' ) ) . '">' . esc_html__( 'همه کارشناسان', 'yadak-core' ) . '</a> — ' . esc_html__( 'عقب‌افتاده، امروز و ۷ روز آینده', 'yadak-core' ) . '</p>';
		if ( ! $items ) {
			echo '<p>' . esc_html__( 'پیگیری بازی ندارید.', 'yadak-core' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'تاریخ', 'yadak-core' ) . '</th><th>' . esc_html__( 'مشتری / سرنخ', 'yadak-core' ) . '</th><th>' . esc_html__( 'شرح', 'yadak-core' ) . '</th>' . ( $all ? '<th>' . esc_html__( 'کارشناس', 'yadak-core' ) . '</th>' : '' ) . '<th></th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$style = $item['date'] < $today ? 'color:#b32d2e;font-weight:700' : ( $item['date'] === $today ? 'font-weight:700' : '' );
			$who   = get_userdata( $item['who'] );
			echo '<tr><td style="' . esc_attr( $style ) . '">' . esc_html( yadak_show_date( $item['date'] ) ) . '</td>';
			echo '<td><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a></td><td>' . esc_html( $item['note'] ) . '</td>';
			if ( $all ) {
				echo '<td>' . esc_html( $who ? $who->display_name : '—' ) . '</td>';
			}
			echo '<td>' . ( $item['done_url'] ? '<a class="button button-small" href="' . esc_url( $item['done_url'] ) . '">' . esc_html__( 'انجام شد', 'yadak-core' ) . '</a>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function render_pipeline() {
		$mine   = ! empty( $_GET['mine'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args   = array(
			'post_type'      => self::LEAD,
			'posts_per_page' => 500,
			'orderby'        => 'modified',
		);
		$args['meta_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		if ( $mine ) {
			$args['meta_query'][] = array(
				'key'   => '_yl_rep',
				'value' => get_current_user_id(),
			);
		}
		$channel = isset( $_GET['channel'] ) ? sanitize_key( $_GET['channel'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $channel ) {
			$args['meta_query'][] = array(
				'key'   => '_yl_channel',
				'value' => $channel,
			);
		}
		$by_stage = array_fill_keys( array_keys( self::stages() ), array() );
		foreach ( get_posts( $args ) as $lead ) {
			$stage                = self::lead_meta( $lead->ID, 'stage' );
			$by_stage[ isset( $by_stage[ $stage ] ) ? $stage : 'lead' ][] = $lead;
		}
		$today = yadak_today();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'قیف فروش', 'yadak-core' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::LEAD ) ); ?>"><?php esc_html_e( 'سرنخ جدید', 'yadak-core' ); ?></a>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=yadak-pipeline' ) ); ?>"><?php esc_html_e( 'همه', 'yadak-core' ); ?></a> | <a href="<?php echo esc_url( admin_url( 'admin.php?page=yadak-pipeline&mine=1' ) ); ?>"><?php esc_html_e( 'فقط من', 'yadak-core' ); ?></a> | <a href="<?php echo esc_url( admin_url( 'admin.php?page=yadak-pipeline&channel=b2b' ) ); ?>">B2B</a> | <a href="<?php echo esc_url( admin_url( 'admin.php?page=yadak-pipeline&channel=b2c' ) ); ?>">B2C</a></p>
			<style>
				.yk-board{display:flex;gap:10px;overflow-x:auto;align-items:flex-start;padding-bottom:10px}
				.yk-col{flex:0 0 220px;background:#f0f0f1;border-radius:8px;padding:8px}
				.yk-col h2{font-size:13px;margin:4px 4px 8px;display:flex;justify-content:space-between}
				.yk-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:8px;margin-bottom:8px;font-size:12px}
				.yk-card a.yk-title{font-weight:700;font-size:13px;display:block;margin-bottom:2px}
				.yk-card .late{color:#b32d2e;font-weight:700}
				.yk-card .dashicons{font-size:14px;width:14px;height:14px;vertical-align:middle;color:#646970}
				.yk-badge{display:inline-block;padding:0 6px;border-radius:4px;background:#1d2327;color:#fff;font-size:10px;font-weight:700}
				.yk-card form{display:flex;gap:4px;margin-top:6px}
				.yk-card select{flex:1;font-size:12px;min-height:26px}
			</style>
			<div class="yk-board">
				<?php foreach ( self::stages() as $stage => $label ) : ?>
					<?php
					$sum = 0;
					foreach ( $by_stage[ $stage ] as $lead ) {
						$sum += (float) self::lead_meta( $lead->ID, 'value' );
					}
					?>
					<div class="yk-col">
						<h2><span><?php echo esc_html( $label ); ?> (<?php echo esc_html( number_format_i18n( count( $by_stage[ $stage ] ) ) ); ?>)</span><span><?php echo $sum ? esc_html( Yadak_SMS::plain_money( $sum ) ) : ''; ?></span></h2>
						<?php foreach ( $by_stage[ $stage ] as $lead ) : ?>
							<?php
							$next = self::lead_meta( $lead->ID, 'next' );
							$rep  = get_userdata( (int) self::lead_meta( $lead->ID, 'rep' ) );
							?>
							<div class="yk-card">
								<a class="yk-title" href="<?php echo esc_url( (string) get_edit_post_link( $lead->ID ) ); ?>"><?php echo esc_html( $lead->post_title ); ?></a>
								<?php if ( self::lead_meta( $lead->ID, 'company' ) ) : ?><div><?php echo esc_html( self::lead_meta( $lead->ID, 'company' ) ); ?></div><?php endif; ?>
								<div><span class="yk-badge"><?php echo esc_html( 'b2c' === self::lead_meta( $lead->ID, 'channel' ) ? 'B2C' : 'B2B' ); ?></span>
								<?php if ( self::lead_meta( $lead->ID, 'value' ) ) : ?> <?php echo esc_html( Yadak_SMS::plain_money( (float) self::lead_meta( $lead->ID, 'value' ) ) ); ?><?php endif; ?></div>
								<?php
								$deal_order = wc_get_order( (int) self::lead_meta( $lead->ID, 'order' ) );
								if ( $deal_order ) {
									echo '<div><span class="dashicons dashicons-cart"></span> <a href="' . esc_url( $deal_order->get_edit_order_url() ) . '">#' . esc_html( $deal_order->get_order_number() ) . '</a> ' . esc_html( wc_get_order_status_name( $deal_order->get_status() ) ) . ( $deal_order->get_meta( '_yadak_tracking' ) ? ' — ' . esc_html__( 'رهگیری:', 'yadak-core' ) . ' ' . esc_html( $deal_order->get_meta( '_yadak_tracking' ) ) : '' ) . '</div>';
								} elseif ( self::lead_meta( $lead->ID, 'quote' ) ) {
									$qid = (int) self::lead_meta( $lead->ID, 'quote' );
									echo '<div><span class="dashicons dashicons-media-text"></span> <a href="' . esc_url( (string) get_edit_post_link( $qid ) ) . '">' . esc_html__( 'پیش‌فاکتور', 'yadak-core' ) . ' #' . (int) $qid . '</a> ' . esc_html( Yadak_Quotes::statuses()[ Yadak_Quotes::status( $qid ) ] ) . '</div>';
								}
								?>
								<?php if ( $rep ) : ?><div><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html( $rep->display_name ); ?></div><?php endif; ?>
								<?php if ( $next ) : ?><div class="<?php echo $next < $today && ! in_array( $stage, array( 'won', 'paid', 'shipped', 'lost' ), true ) ? 'late' : ''; ?>"><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html( yadak_show_date( $next ) ); ?></div><?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="yadak_lead_stage">
									<input type="hidden" name="lead" value="<?php echo esc_attr( $lead->ID ); ?>">
									<?php wp_nonce_field( 'yadak_lead_stage_' . $lead->ID ); ?>
									<select name="stage" aria-label="<?php esc_attr_e( 'مرحله', 'yadak-core' ); ?>">
										<?php foreach ( self::stages() as $key => $name ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $stage, $key ); ?>><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</select>
									<button class="button button-small"><?php esc_html_e( 'انتقال', 'yadak-core' ); ?></button>
								</form>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public static function dashboard_widget() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'yadak_followups',
			__( 'پیگیری‌های امروز', 'yadak-core' ),
			static function () {
				$items = self::due_items( get_current_user_id(), yadak_today() );
				if ( ! $items ) {
					echo '<p>' . esc_html__( 'پیگیری عقب‌افتاده یا امروزی ندارید.', 'yadak-core' ) . '</p>';
				} else {
					echo '<ul>';
					foreach ( array_slice( $items, 0, 8 ) as $item ) {
						echo '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a> — ' . esc_html( wp_trim_words( $item['note'], 10 ) ) . '</li>';
					}
					echo '</ul>';
				}
				echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=yadak-followups' ) ) . '">' . esc_html__( 'همه پیگیری‌ها', 'yadak-core' ) . '</a></p>';
			}
		);
	}

	/* ---------- Customer 360 ---------- */

	/**
	 * @return array Stats for a customer.
	 */
	public static function customer_stats( $user_id ) {
		$count = wc_get_customer_order_count( $user_id );
		$spent = (float) wc_get_customer_total_spent( $user_id );
		$last  = wc_get_customer_last_order( $user_id );
		$cats  = array();
		foreach ( wc_get_orders( array( 'customer_id' => $user_id, 'limit' => 20, 'status' => array( 'processing', 'completed' ) ) ) as $order ) {
			foreach ( $order->get_items() as $item ) {
				foreach ( wc_get_product_term_ids( $item->get_product_id(), 'product_cat' ) as $cat_id ) {
					$cats[ $cat_id ] = ( $cats[ $cat_id ] ?? 0 ) + $item->get_quantity();
				}
			}
		}
		arsort( $cats );
		$names = array();
		foreach ( array_slice( array_keys( $cats ), 0, 3 ) as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return array(
			'count'   => $count,
			'spent'   => $spent,
			'aov'     => $count ? $spent / $count : 0,
			'last'    => $last && $last->get_date_created() ? $last->get_date_created()->getTimestamp() : 0,
			'balance' => Yadak_Credit::balance( $user_id ),
			'cats'    => $names,
		);
	}

	/**
	 * @param WP_User $user User.
	 */
	public static function customer_360( $user ) {
		if ( ! current_user_can( 'edit_shop_orders' ) || ! in_array( 'customer', (array) $user->roles, true ) && ! get_user_meta( $user->ID, 'yadak_customer_group', true ) ) {
			return;
		}
		$s = self::customer_stats( $user->ID );
		wp_nonce_field( 'yadak_user_activity', 'yadak_user_activity_nonce' );
		$cells = array(
			__( 'تعداد سفارش', 'yadak-core' )     => number_format_i18n( $s['count'] ),
			__( 'مجموع خرید', 'yadak-core' )      => Yadak_SMS::plain_money( $s['spent'] ),
			__( 'میانگین هر سفارش', 'yadak-core' ) => Yadak_SMS::plain_money( $s['aov'] ),
			__( 'آخرین خرید', 'yadak-core' )      => $s['last'] ? wp_date( 'Y/m/d', $s['last'] ) . ' (' . human_time_diff( $s['last'] ) . ')' : '—',
			__( 'مانده حساب', 'yadak-core' )      => Yadak_SMS::plain_money( max( 0, $s['balance'] ) ),
			__( 'دسته‌های پرخرید', 'yadak-core' ) => $s['cats'] ? implode( '، ', $s['cats'] ) : '—',
		);
		echo '<h2 id="yadak-360">' . esc_html__( 'پرونده مشتری', 'yadak-core' ) . '</h2>';
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-bottom:12px">';
		foreach ( $cells as $label => $value ) {
			echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px"><div style="color:#646970;font-size:12px">' . esc_html( $label ) . '</div><strong>' . esc_html( $value ) . '</strong></div>';
		}
		echo '</div><p>';
		echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $user->ID ) ) . '">' . esc_html__( 'سفارش‌ها', 'yadak-core' ) . '</a> ';
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-orders&_customer_user=' . $user->ID ) ) . '">' . esc_html__( 'سفارش‌ها (HPOS)', 'yadak-core' ) . '</a> ';
		}
		echo '<a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=' . Yadak_Quotes::POST_TYPE . '&yq_customer=' . $user->ID ) ) . '">' . esc_html__( 'پیش‌فاکتور جدید', 'yadak-core' ) . '</a>';
		echo '</p><h3>' . esc_html__( 'فعالیت‌ها و پیگیری', 'yadak-core' ) . '</h3>';
		self::render_activities( 'user', $user->ID );
	}

	public static function save_user_activity( $user_id ) {
		if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_POST['yadak_user_activity_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['yadak_user_activity_nonce'] ), 'yadak_user_activity' ) ) {
			return;
		}
		$rep = (int) get_user_meta( $user_id, 'yadak_sales_rep', true );
		self::save_posted_activity( 'user', $user_id, $rep ? $rep : get_current_user_id() );
	}

	/* ---------- Segments ---------- */

	public static function segments() {
		return array(
			''         => __( 'همه بخش‌ها', 'yadak-core' ),
			'inactive' => __( 'غیرفعال (۹۰ روز بدون خرید)', 'yadak-core' ),
			'debtor'   => __( 'بدهکار', 'yadak-core' ),
			'credit'   => __( 'دارای اعتبار', 'yadak-core' ),
			'pending'  => __( 'درخواست همکاری در انتظار', 'yadak-core' ),
			'mine'     => __( 'مشتریان من (کارشناس)', 'yadak-core' ),
		);
	}

	public static function user_filters( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$group   = isset( $_GET['yadak_group'] ) ? sanitize_key( $_GET['yadak_group'] ) : '';
		$segment = isset( $_GET['yadak_segment'] ) ? sanitize_key( $_GET['yadak_segment'] ) : '';
		// phpcs:enable
		echo '<div class="alignleft actions" style="margin-inline-start:8px"><select name="yadak_group"><option value="">' . esc_html__( 'همه انواع مشتری', 'yadak-core' ) . '</option>';
		foreach ( yadak_customer_groups() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $group, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><select name="yadak_segment">';
		foreach ( self::segments() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $segment, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'فیلتر', 'yadak-core' ), '', 'yadak_filter', false );
		echo '</div>';
	}

	/**
	 * @param WP_User_Query $query Query.
	 */
	public static function filter_users( $query ) {
		global $pagenow, $wpdb;
		if ( ! is_admin() || 'users.php' !== $pagenow ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$group   = isset( $_GET['yadak_group'] ) ? sanitize_key( $_GET['yadak_group'] ) : '';
		$segment = isset( $_GET['yadak_segment'] ) ? sanitize_key( $_GET['yadak_segment'] ) : '';
		// phpcs:enable
		$meta = (array) $query->get( 'meta_query' );
		if ( $group ) {
			$meta[] = 'retail' === $group
				? array(
					'relation' => 'OR',
					array( 'key' => 'yadak_customer_group', 'value' => 'retail' ),
					array( 'key' => 'yadak_customer_group', 'compare' => 'NOT EXISTS' ),
				)
				: array( 'key' => 'yadak_customer_group', 'value' => $group );
		}
		switch ( $segment ) {
			case 'inactive':
				$query->set( 'role', 'customer' );
				$meta[] = array(
					'relation' => 'OR',
					array( 'key' => 'yadak_last_order', 'value' => gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) ), 'compare' => '<', 'type' => 'DATETIME' ),
					array( 'key' => 'yadak_last_order', 'compare' => 'NOT EXISTS' ),
				);
				break;
			case 'debtor':
				$table = Yadak_Credit::table();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ids = $wpdb->get_col( "SELECT user_id FROM {$table} GROUP BY user_id HAVING SUM(CASE WHEN type = 'charge' THEN amount ELSE -amount END) > 0" );
				$query->set( 'include', $ids ? array_map( 'intval', $ids ) : array( 0 ) );
				break;
			case 'credit':
				$meta[] = array( 'key' => 'yadak_credit_limit', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' );
				break;
			case 'pending':
				$meta[] = array( 'key' => 'yadak_group_requested', 'compare' => 'EXISTS' );
				break;
			case 'mine':
				$meta[] = array( 'key' => 'yadak_sales_rep', 'value' => get_current_user_id() );
				break;
		}
		if ( count( $meta ) > 0 && array_filter( $meta ) ) {
			$query->set( 'meta_query', array_filter( $meta ) );
		}
	}

	/**
	 * Fill yadak_last_order for existing customers (on activation).
	 */
	public static function backfill_last_orders() {
		foreach ( wc_get_orders( array( 'limit' => 3000, 'status' => array( 'processing', 'completed' ), 'orderby' => 'date', 'order' => 'DESC' ) ) as $order ) {
			$customer = $order->get_customer_id();
			if ( $customer && ! get_user_meta( $customer, 'yadak_last_order', true ) && $order->get_date_created() ) {
				update_user_meta( $customer, 'yadak_last_order', gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) );
			}
		}
	}
}
