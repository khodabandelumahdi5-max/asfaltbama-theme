<?php
/**
 * Customer profile: group (retail / mechanic / repair shop / fleet / dealer /
 * wholesale), credit limit, sales representative, business details and a
 * private sales note. B2B groups are requested at registration and approved
 * by a shop manager.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Customers {

	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );

		add_action( 'woocommerce_register_form', array( __CLASS__, 'register_fields' ) );
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'save_registration' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'account_dashboard' ) );
	}

	/**
	 * Can the current user manage customer business data?
	 *
	 * @return bool
	 */
	private static function can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Users who can act as sales representatives.
	 *
	 * @return WP_User[]
	 */
	public static function sales_reps() {
		return get_users(
			array(
				'role__in' => apply_filters( 'yadak_sales_rep_roles', array( 'administrator', 'shop_manager' ) ),
				'orderby'  => 'display_name',
			)
		);
	}

	/**
	 * @param WP_User $user User being edited.
	 */
	public static function profile_fields( $user ) {
		if ( ! self::can_manage() ) {
			return;
		}
		$group     = yadak_get_customer_group( $user->ID );
		$requested = get_user_meta( $user->ID, 'yadak_group_requested', true );
		$rep       = (int) get_user_meta( $user->ID, 'yadak_sales_rep', true );
		wp_nonce_field( 'yadak_profile', 'yadak_profile_nonce' );
		?>
		<h2><?php esc_html_e( 'اطلاعات تجاری مشتری', 'yadak-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="yadak_customer_group"><?php esc_html_e( 'نوع مشتری', 'yadak-core' ); ?></label></th>
				<td>
					<select name="yadak_customer_group" id="yadak_customer_group">
						<?php foreach ( yadak_customer_groups() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $group, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( $requested && $requested !== $group ) : ?>
						<p class="description" style="color:#b32d2e">
							<?php
							$groups = yadak_customer_groups();
							printf(
								/* translators: %s: requested group */
								esc_html__( 'درخواست مشتری در ثبت‌نام: %s — برای تأیید، همین نوع را انتخاب و ذخیره کنید.', 'yadak-core' ),
								esc_html( isset( $groups[ $requested ] ) ? $groups[ $requested ] : $requested )
							);
							?>
						</p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'نوع مشتری سطح قیمت او را تعیین می‌کند (مکانیک/تعمیرگاه/ناوگان: قیمت همکار — فروشنده/عمده‌فروش: قیمت عمده).', 'yadak-core' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="yadak_credit_limit"><?php esc_html_e( 'سقف اعتبار (تومان)', 'yadak-core' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" name="yadak_credit_limit" id="yadak_credit_limit" value="<?php echo esc_attr( (string) (float) get_user_meta( $user->ID, 'yadak_credit_limit', true ) ); ?>" class="regular-text" />
					<?php if ( class_exists( 'Yadak_Credit' ) ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: 1: used credit, 2: available credit */
								esc_html__( 'استفاده‌شده: %1$s — قابل استفاده: %2$s', 'yadak-core' ),
								wp_kses_post( yadak_money( Yadak_Credit::used_credit( $user->ID ) ) ),
								wp_kses_post( yadak_money( Yadak_Credit::available_credit( $user->ID ) ) )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="yadak_sales_rep"><?php esc_html_e( 'فروشنده اختصاصی', 'yadak-core' ); ?></label></th>
				<td>
					<select name="yadak_sales_rep" id="yadak_sales_rep">
						<option value="0"><?php esc_html_e( '— ندارد —', 'yadak-core' ); ?></option>
						<?php foreach ( self::sales_reps() as $rep_user ) : ?>
							<option value="<?php echo esc_attr( $rep_user->ID ); ?>" <?php selected( $rep, $rep_user->ID ); ?>><?php echo esc_html( $rep_user->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="yadak_company"><?php esc_html_e( 'نام فروشگاه / شرکت', 'yadak-core' ); ?></label></th>
				<td><input type="text" name="yadak_company" id="yadak_company" value="<?php echo esc_attr( get_user_meta( $user->ID, 'yadak_company', true ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="yadak_national_id"><?php esc_html_e( 'کد ملی / شناسه ملی', 'yadak-core' ); ?></label></th>
				<td><input type="text" name="yadak_national_id" id="yadak_national_id" value="<?php echo esc_attr( get_user_meta( $user->ID, 'yadak_national_id', true ) ); ?>" class="regular-text" dir="ltr" /></td>
			</tr>
			<tr>
				<th><label for="yadak_economic_code"><?php esc_html_e( 'کد اقتصادی (فاکتور رسمی)', 'yadak-core' ); ?></label></th>
				<td><input type="text" name="yadak_economic_code" id="yadak_economic_code" value="<?php echo esc_attr( get_user_meta( $user->ID, 'yadak_economic_code', true ) ); ?>" class="regular-text" dir="ltr" /></td>
			</tr>
			<tr>
				<th><label for="yadak_sales_note"><?php esc_html_e( 'یادداشت فروش (فقط داخلی)', 'yadak-core' ); ?></label></th>
				<td><textarea name="yadak_sales_note" id="yadak_sales_note" rows="4" class="large-text"><?php echo esc_textarea( get_user_meta( $user->ID, 'yadak_sales_note', true ) ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * @param int $user_id User.
	 */
	public static function save_profile( $user_id ) {
		if ( ! self::can_manage() || ! isset( $_POST['yadak_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['yadak_profile_nonce'] ), 'yadak_profile' ) ) {
			return;
		}

		$group = isset( $_POST['yadak_customer_group'] ) ? sanitize_key( $_POST['yadak_customer_group'] ) : 'retail';
		if ( array_key_exists( $group, yadak_customer_groups() ) ) {
			$old = yadak_get_customer_group( $user_id );
			update_user_meta( $user_id, 'yadak_customer_group', $group );
			if ( $old !== $group ) {
				do_action( 'yadak_customer_group_changed', $user_id, $group, $old );
			}
		}

		$limit = isset( $_POST['yadak_credit_limit'] ) ? max( 0, (float) wc_format_decimal( wp_unslash( $_POST['yadak_credit_limit'] ) ) ) : 0;
		update_user_meta( $user_id, 'yadak_credit_limit', $limit );

		update_user_meta( $user_id, 'yadak_sales_rep', isset( $_POST['yadak_sales_rep'] ) ? absint( $_POST['yadak_sales_rep'] ) : 0 );

		foreach ( array( 'yadak_company', 'yadak_national_id', 'yadak_economic_code' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_user_meta( $user_id, $key, sanitize_text_field( yadak_normalize_digits( wp_unslash( $_POST[ $key ] ) ) ) );
			}
		}
		if ( isset( $_POST['yadak_sales_note'] ) ) {
			update_user_meta( $user_id, 'yadak_sales_note', sanitize_textarea_field( wp_unslash( $_POST['yadak_sales_note'] ) ) );
		}
	}

	public static function users_column( $columns ) {
		$columns['yadak_group'] = __( 'نوع مشتری', 'yadak-core' );
		return $columns;
	}

	public static function users_column_value( $value, $column, $user_id ) {
		if ( 'yadak_group' !== $column ) {
			return $value;
		}
		$groups    = yadak_customer_groups();
		$group     = yadak_get_customer_group( $user_id );
		$requested = get_user_meta( $user_id, 'yadak_group_requested', true );
		$out       = esc_html( $groups[ $group ] );
		if ( $requested && $requested !== $group && isset( $groups[ $requested ] ) ) {
			/* translators: %s: requested group */
			$out .= '<br><span style="color:#b32d2e">' . esc_html( sprintf( __( 'درخواست: %s', 'yadak-core' ), $groups[ $requested ] ) ) . '</span>';
		}
		return $out;
	}

	/**
	 * Extra fields on the My Account registration form.
	 */
	public static function register_fields() {
		?>
		<p class="form-row form-row-wide">
			<label for="yadak_group_requested"><?php esc_html_e( 'نوع خرید', 'yadak-core' ); ?></label>
			<select name="yadak_group_requested" id="yadak_group_requested">
				<?php foreach ( yadak_customer_groups() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'حساب‌های همکار و عمده پس از بررسی کارشناس فعال می‌شوند.', 'yadak-core' ); ?></span>
		</p>
		<p class="form-row form-row-wide">
			<label for="yadak_company"><?php esc_html_e( 'نام فروشگاه / تعمیرگاه / شرکت (اختیاری)', 'yadak-core' ); ?></label>
			<input type="text" class="input-text" name="yadak_company" id="yadak_company" />
		</p>
		<?php
	}

	/**
	 * @param int $customer_id New customer.
	 */
	public static function save_registration( $customer_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the registration nonce.
		update_user_meta( $customer_id, 'yadak_customer_group', 'retail' );
		$requested = isset( $_POST['yadak_group_requested'] ) ? sanitize_key( $_POST['yadak_group_requested'] ) : 'retail';
		if ( ! empty( $_POST['yadak_company'] ) ) {
			update_user_meta( $customer_id, 'yadak_company', sanitize_text_field( wp_unslash( $_POST['yadak_company'] ) ) );
		}
		// phpcs:enable
		if ( 'retail' === $requested || ! array_key_exists( $requested, yadak_customer_groups() ) ) {
			return;
		}
		update_user_meta( $customer_id, 'yadak_group_requested', $requested );

		$groups = yadak_customer_groups();
		$user   = get_userdata( $customer_id );
		wp_mail(
			get_option( 'admin_email' ),
			__( 'درخواست حساب همکار جدید', 'yadak-core' ),
			sprintf(
				/* translators: 1: user login, 2: group, 3: edit link */
				__( "کاربر %1\$s درخواست حساب «%2\$s» داده است.\nبررسی و تأیید: %3\$s", 'yadak-core' ),
				$user ? $user->user_login : $customer_id,
				$groups[ $requested ],
				admin_url( 'user-edit.php?user_id=' . $customer_id )
			)
		);
		do_action( 'yadak_b2b_requested', $customer_id, $requested );
	}

	/**
	 * Group, pending request and sales rep on the account dashboard.
	 */
	public static function account_dashboard() {
		$user_id   = get_current_user_id();
		$groups    = yadak_customer_groups();
		$group     = yadak_get_customer_group( $user_id );
		$requested = get_user_meta( $user_id, 'yadak_group_requested', true );
		$rep       = get_userdata( (int) get_user_meta( $user_id, 'yadak_sales_rep', true ) );

		echo '<div class="yadak-account-box">';
		/* translators: %s: customer group */
		echo '<p>' . esc_html( sprintf( __( 'نوع حساب: %s', 'yadak-core' ), $groups[ $group ] ) ) . '</p>';
		if ( $requested && $requested !== $group && isset( $groups[ $requested ] ) ) {
			/* translators: %s: requested group */
			echo '<p>' . esc_html( sprintf( __( 'درخواست حساب «%s» شما در حال بررسی است.', 'yadak-core' ), $groups[ $requested ] ) ) . '</p>';
		}
		if ( $rep ) {
			/* translators: %s: sales rep name */
			echo '<p>' . esc_html( sprintf( __( 'کارشناس فروش شما: %s', 'yadak-core' ), $rep->display_name ) ) . '</p>';
		}
		echo '</div>';
	}
}
