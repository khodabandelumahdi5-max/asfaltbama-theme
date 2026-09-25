<?php
/**
 * Tables for CRM activities, replacement reminders, SMS log and warehouse
 * stock moves, plus the daily cron event.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Yadak_Install {

	const DB_VERSION = '2';

	public static function init() {
		if ( get_option( 'yadak_core_db' ) !== self::DB_VERSION ) {
			self::install();
		}
		if ( ! wp_next_scheduled( 'yadak_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 09:00:00' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'yadak_daily' );
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'yadak_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . self::table( 'activity' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				object_type varchar(16) NOT NULL,
				object_id bigint(20) unsigned NOT NULL,
				type varchar(16) NOT NULL,
				note text NOT NULL,
				due_date date DEFAULT NULL,
				done tinyint(1) NOT NULL DEFAULT 0,
				assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY object (object_type, object_id),
				KEY due (done, due_date)
			) {$c};"
		);
		dbDelta(
			'CREATE TABLE ' . self::table( 'reminders' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				product_id bigint(20) unsigned NOT NULL,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				due_date date NOT NULL,
				sent tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY due (sent, due_date)
			) {$c};"
		);
		dbDelta(
			'CREATE TABLE ' . self::table( 'sms_log' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				mobile varchar(20) NOT NULL,
				message text NOT NULL,
				event varchar(40) NOT NULL DEFAULT '',
				status varchar(16) NOT NULL DEFAULT '',
				response text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at)
			) {$c};"
		);
		dbDelta(
			'CREATE TABLE ' . self::table( 'stock_moves' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				warehouse varchar(32) NOT NULL,
				qty decimal(20,3) NOT NULL,
				reason varchar(32) NOT NULL DEFAULT '',
				note varchar(255) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY product_id (product_id)
			) {$c};"
		);
		update_option( 'yadak_core_db', self::DB_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'yadak_daily' );
	}
}
