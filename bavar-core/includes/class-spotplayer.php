<?php
/**
 * Optional SpotPlayer licence creation for course products.
 * Runs only when an API key is set in BAVAR → Settings and the course
 * product has a SpotPlayer course ID.
 *
 * @package BavarCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bavar_SpotPlayer {

	const ENDPOINT = 'https://panel.spotplayer.ir/license/edit/';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'issue' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'issue' ] );
	}

	/**
	 * Create licences for paid course items (idempotent).
	 *
	 * @param int $order_id Order ID.
	 */
	public static function issue( $order_id ) {
		$api_key = trim( (string) Bavar_Settings::get( 'spot_api_key' ) );
		$order   = wc_get_order( $order_id );
		if ( ! $api_key || ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$course = trim( (string) get_post_meta( $item->get_product_id(), '_bavar_spot_course', true ) );
			if ( ! $course || $item->get_meta( '_bavar_spot_key' ) ) {
				continue;
			}

			$phone = $order->get_billing_phone();
			$body  = [
				'test'      => false,
				'course'    => [ $course ],
				'offline'   => max( 0, (int) Bavar_Settings::get( 'spot_offline' ) ),
				'name'      => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: $phone,
				'payload'   => (string) $order->get_id(),
				'watermark' => [ 'texts' => [ [ 'text' => $phone ] ] ],
			];

			$response = wp_remote_post(
				self::ENDPOINT,
				[
					'timeout' => 20,
					'headers' => [
						'$API'         => $api_key,
						'$LEVEL'       => '-1',
						'Content-Type' => 'application/json',
					],
					'body'    => wp_json_encode( $body ),
				]
			);

			$data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $data['key'] ) ) {
				$reason = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
				$order->add_order_note( 'ساخت لایسنس اسپات‌پلیر ناموفق بود: ' . mb_substr( wp_strip_all_tags( (string) $reason ), 0, 300 ) );
				continue;
			}

			$item->update_meta_data( '_bavar_spot_key', sanitize_text_field( $data['key'] ) );
			$item->update_meta_data( '_bavar_spot_url', esc_url_raw( $data['url'] ?? '' ) );
			$item->update_meta_data( '_bavar_spot_id', sanitize_text_field( $data['_id'] ?? '' ) );
			$item->save();
			$order->add_order_note( 'لایسنس اسپات‌پلیر ساخته شد.' );
		}
	}
}
