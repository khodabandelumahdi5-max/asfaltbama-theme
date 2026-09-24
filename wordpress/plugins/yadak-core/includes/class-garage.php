<?php
/**
 * "My garage" in My Account: customers keep a list of their vehicles and
 * jump to the parts that fit each one. Any vehicle a logged-in customer picks
 * in the vehicle selector is added automatically.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Garage {

	const ENDPOINT = 'garage';
	const META     = 'yadak_garage';
	const MAX      = 10;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_action( 'yadak_vehicle_selected', array( __CLASS__, 'remember_selection' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_remove' ) );
	}

	public static function add_endpoints() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function title() {
		return __( 'گاراژ من', 'yadak-core' );
	}

	public static function menu_item( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = self::title();
			}
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = self::title();
		}
		return $new;
	}

	/**
	 * @param int $user_id User.
	 * @return int[] Vehicle term IDs.
	 */
	public static function vehicles( $user_id ) {
		$ids = get_user_meta( $user_id, self::META, true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	public static function add( $user_id, $term_id ) {
		$ids = self::vehicles( $user_id );
		$ids = array_values( array_diff( $ids, array( (int) $term_id ) ) );
		array_unshift( $ids, (int) $term_id );
		update_user_meta( $user_id, self::META, array_slice( $ids, 0, self::MAX ) );
	}

	/**
	 * @param WP_Term $term Picked vehicle.
	 */
	public static function remember_selection( $term ) {
		if ( is_user_logged_in() ) {
			self::add( get_current_user_id(), $term->term_id );
		}
	}

	public static function handle_remove() {
		if ( ! isset( $_POST['yadak_garage_remove'], $_POST['_wpnonce'] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'yadak_garage' ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$remove  = absint( $_POST['yadak_garage_remove'] );
		update_user_meta( $user_id, self::META, array_values( array_diff( self::vehicles( $user_id ), array( $remove ) ) ) );
		$current = Yadak_Fitment::current_vehicle();
		if ( $current && $current->term_id === $remove ) {
			Yadak_Fitment::set_current_vehicle( 0 );
		}
		wc_add_notice( __( 'خودرو از گاراژ حذف شد.', 'yadak-core' ) );
		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}

	public static function render() {
		$current = Yadak_Fitment::current_vehicle();
		$ids     = self::vehicles( get_current_user_id() );

		echo '<div class="yadak-garage">';
		if ( $ids ) {
			echo '<ul class="yadak-garage__list">';
			foreach ( $ids as $id ) {
				$term = get_term( $id, Yadak_Fitment::TAXONOMY );
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$active = $current && $current->term_id === $term->term_id;
				?>
				<li class="yadak-garage__item<?php echo $active ? ' is-active' : ''; ?>">
					<span class="yadak-garage__name"><?php echo esc_html( Yadak_Fitment::path( $term ) ); ?></span>
					<?php if ( $active ) : ?>
						<span class="yadak-garage__badge"><?php esc_html_e( 'خودروی فعال', 'yadak-core' ); ?></span>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'yadak_vehicle', $term->term_id, home_url( '/' ) ) ); ?>"><?php esc_html_e( 'قطعات این خودرو', 'yadak-core' ); ?></a>
					<form method="post" class="yadak-garage__remove">
						<?php wp_nonce_field( 'yadak_garage' ); ?>
						<button type="submit" name="yadak_garage_remove" value="<?php echo esc_attr( $term->term_id ); ?>" class="button-link"><?php esc_html_e( 'حذف', 'yadak-core' ); ?></button>
					</form>
				</li>
				<?php
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'هنوز خودرویی ثبت نکرده‌اید. خودروی خود را انتخاب کنید تا همیشه فقط قطعات سازگار را ببینید.', 'yadak-core' ) . '</p>';
		}
		echo do_shortcode(
			'[yadak_vehicle_selector title="' . esc_attr__( 'افزودن خودرو', 'yadak-core' ) . '" button="' . esc_attr__( 'افزودن و نمایش قطعات', 'yadak-core' ) . '"]'
		);
		echo '</div>';
	}
}
