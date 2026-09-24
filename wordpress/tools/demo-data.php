<?php
/**
 * Demo catalogue for trying the store locally. NOT for production.
 *
 * Usage (from the WordPress root, with Yadak Core active):
 *   wp eval-file path/to/demo-data.php
 *
 * All names, part numbers and prices below are made-up sample data.
 *
 * @package YadakCore
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'Yadak_Fitment' ) ) {
	echo "Run with: wp eval-file demo-data.php (Yadak Core must be active)\n";
	return;
}

/**
 * Create (or find) a term path like array( 'پژو', '206', 'تیپ ۵ (TU5)' ).
 *
 * @param string[] $path     Names from top to bottom.
 * @param string   $taxonomy Taxonomy.
 * @return int Deepest term ID.
 */
$yadak_demo_term = static function ( array $path, $taxonomy ) {
	$parent = 0;
	foreach ( $path as $name ) {
		$found = get_terms( array( 'taxonomy' => $taxonomy, 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( $found && ! is_wp_error( $found ) ) {
			$parent = (int) $found[0];
			continue;
		}
		$made   = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
		$parent = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
	}
	return $parent;
};

$vehicles = array(
	'206_tu3' => array( 'پژو', '206', 'تیپ ۲ (TU3)' ),
	'206_tu5' => array( 'پژو', '206', 'تیپ ۵ (TU5)' ),
	'206'     => array( 'پژو', '206' ),
	'pars'    => array( 'پژو', 'پارس' ),
	'405'     => array( 'پژو', '405' ),
	'samand'  => array( 'ایران‌خودرو', 'سمند', 'EF7' ),
	'dena'    => array( 'ایران‌خودرو', 'دنا' ),
	'pride'   => array( 'سایپا', 'پراید' ),
	'tiba'    => array( 'سایپا', 'تیبا' ),
	'quick'   => array( 'سایپا', 'کوییک' ),
	'l90'     => array( 'رنو', 'L90' ),
	'mvm'     => array( 'ام‌وی‌ام', 'X22' ),
);
$v = array();
foreach ( $vehicles as $key => $path ) {
	$v[ $key ] = $yadak_demo_term( $path, Yadak_Fitment::TAXONOMY );
}

$cats = array();
foreach ( array( 'ترمز', 'فیلتر', 'جلوبندی و تعلیق', 'برق خودرو', 'قطعات موتور', 'روغن و روانکار', 'کلاچ و گیربکس', 'بدنه و چراغ' ) as $i => $name ) {
	$cats[ $name ] = $yadak_demo_term( array( $name ), 'product_cat' );
	wp_update_term( $cats[ $name ], 'product_cat', array() );
	update_term_meta( $cats[ $name ], 'order', $i );
}
$brand = static function ( $name ) use ( $yadak_demo_term ) {
	return taxonomy_exists( 'product_brand' ) ? $yadak_demo_term( array( $name ), 'product_brand' ) : 0;
};

$products = array(
	array( 'لنت ترمز جلو پژو ۲۰۶', 'ترمز', 'نمونه-الف', 'DEMO-BP-2061', "DEMO-OEM-4254-C1\nDEMO-XR-0017", 'لنت جلو، lent, brake pad', 'aftermarket', 1850000, 1650000, 1520000, 40, array( '206' ) ),
	array( 'دیسک ترمز جلو پژو ۲۰۶ تیپ ۵', 'ترمز', 'نمونه-الف', 'DEMO-BD-2065', 'DEMO-OEM-4249-J9', 'دیسک چرخ، disc', 'oem', 3950000, 3600000, 3400000, 12, array( '206_tu5' ) ),
	array( 'لنت ترمز جلو پراید', 'ترمز', 'نمونه-ب', 'DEMO-BP-PR01', 'DEMO-OEM-S4110', 'لنت جلو پراید, lent pride', 'aftermarket', 980000, 880000, 820000, 60, array( 'pride', 'tiba', 'quick' ) ),
	array( 'فیلتر روغن پژو و سمند', 'فیلتر', 'نمونه-ج', 'DEMO-OF-1109', "DEMO-OEM-1109-AY\nDEMO-XR-0331", 'فیلتر روغن, oil filter', 'aftermarket', 420000, 370000, 340000, 150, array( '206', 'pars', '405', 'samand', 'dena' ) ),
	array( 'فیلتر هوا پژو ۲۰۶', 'فیلتر', 'نمونه-ج', 'DEMO-AF-2060', 'DEMO-OEM-1444-TK', 'فیلتر هوا, air filter', 'aftermarket', 390000, 350000, 320000, 3, array( '206' ) ),
	array( 'کمک فنر جلو پراید (جفت)', 'جلوبندی و تعلیق', 'نمونه-د', 'DEMO-SA-PR02', 'DEMO-OEM-S5470', 'کمک جلو, komak', 'oem', 5200000, 4750000, 4500000, 8, array( 'pride', 'tiba' ) ),
	array( 'سیبک فرمان پژو ۴۰۵ و پارس', 'جلوبندی و تعلیق', 'نمونه-د', 'DEMO-TR-4051', 'DEMO-OEM-3817-43', 'سیبک, sibak', 'aftermarket', 760000, 690000, 650000, 25, array( '405', 'pars' ) ),
	array( 'باتری ۶۰ آمپر', 'برق خودرو', 'نمونه-ه', 'DEMO-BT-0060', '', 'باطری, battery', 'genuine', 7400000, 6900000, 6600000, 10, array( '206', 'pars', '405', 'samand', 'dena', 'l90' ) ),
	array( 'شمع موتور (بسته ۴ عددی) TU5', 'قطعات موتور', 'نمونه-و', 'DEMO-SP-TU54', 'DEMO-OEM-5960-K8', 'شمع, spark plug, sham', 'oem', 1450000, 1300000, 1220000, 30, array( '206_tu5' ) ),
	array( 'تسمه تایم پژو ۲۰۶ تیپ ۲', 'قطعات موتور', 'نمونه-و', 'DEMO-TB-TU31', 'DEMO-OEM-0816-G9', 'تسمه تایم, timing belt', 'aftermarket', 1150000, 1030000, 980000, 18, array( '206_tu3' ) ),
	array( 'روغن موتور 10W-40 چهار لیتری', 'روغن و روانکار', 'نمونه-ز', 'DEMO-OL-1040', '', 'روغن, oil, 10w40', 'genuine', 2350000, 2150000, 2050000, 80, array() ),
	array( 'دیسک و صفحه کلاچ کوییک', 'کلاچ و گیربکس', 'نمونه-ح', 'DEMO-CL-QK01', 'DEMO-OEM-QK-2210', 'کلاچ, clutch', 'aftermarket', 6100000, 5600000, 5300000, 5, array( 'quick' ) ),
);

$count = 0;
foreach ( $products as $p ) {
	list( $name, $cat, $brand_name, $pn, $oem, $alt, $quality, $price, $b2b, $dealer, $stock, $fits ) = $p;
	$existing = wc_get_product_id_by_sku( $pn );
	$product  = $existing ? wc_get_product( $existing ) : new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_sku( $pn );
	$product->set_status( 'publish' );
	$product->set_regular_price( (string) $price );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $stock );
	$product->set_low_stock_amount( 5 );
	$product->set_category_ids( array( $cats[ $cat ] ) );
	$product->set_short_description( 'محصول نمونه برای آزمایش فروشگاه — اطلاعات واقعی نیست.' );
	$product->update_meta_data( '_yadak_part_number', $pn );
	$product->update_meta_data( '_yadak_oem_numbers', $oem );
	$product->update_meta_data( '_yadak_alt_names', $alt );
	$product->update_meta_data( '_yadak_quality', $quality );
	$product->update_meta_data( '_yadak_warranty', '۶ ماه ضمانت سلامت (نمونه)' );
	$product->update_meta_data( '_yadak_price_b2b', (string) $b2b );
	$product->update_meta_data( '_yadak_price_dealer', (string) $dealer );
	$id = $product->save();
	wp_set_object_terms( $id, array_map( static function ( $k ) use ( $v ) { return $v[ $k ]; }, $fits ), Yadak_Fitment::TAXONOMY );
	if ( $brand( $brand_name ) ) {
		wp_set_object_terms( $id, array( $brand( $brand_name ) ), 'product_brand' );
	}
	Yadak_Search::index( $id );
	++$count;
}

echo "Demo data ready: {$count} products.\n";
