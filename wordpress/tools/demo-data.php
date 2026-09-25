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

// make (fa) => [slug, origin, [model (fa) => [slug, [variants]]]].
$makes = array(
	'چری'       => array( 'chery', 'cn', array( 'تیگو ۷' => array( 'tiggo-7', array() ), 'تیگو ۸ پرو' => array( 'tiggo-8-pro', array() ), 'آریزو ۵' => array( 'arrizo-5', array() ) ) ),
	'ام‌وی‌ام'  => array( 'mvm', 'cn', array( 'X22' => array( 'x22', array() ), 'X33' => array( 'x33', array( 'X33 S', 'X33 Cross' ) ), '315' => array( '315', array() ) ) ),
	'جک'        => array( 'jac', 'cn', array( 'S5' => array( 's5', array() ), 'J4' => array( 'j4', array() ) ) ),
	'هاوال'     => array( 'haval', 'cn', array( 'H6' => array( 'h6', array() ) ) ),
	'تویوتا'    => array( 'toyota', 'jp', array( 'کرولا' => array( 'corolla', array( '1.8', '1.6' ) ), 'کمری' => array( 'camry', array() ), 'پرادو' => array( 'prado', array() ) ) ),
	'نیسان'     => array( 'nissan', 'jp', array( 'قشقایی' => array( 'qashqai', array() ), 'ماکسیما' => array( 'maxima', array() ), 'جوک' => array( 'juke', array() ) ) ),
	'مزدا'      => array( 'mazda', 'jp', array( 'مزدا ۳' => array( 'mazda-3', array() ) ) ),
	'میتسوبیشی' => array( 'mitsubishi', 'jp', array( 'پاجرو' => array( 'pajero', array() ), 'ASX' => array( 'asx', array() ) ) ),
	'هیوندای'   => array( 'hyundai', 'kr', array( 'النترا' => array( 'elantra', array() ), 'سوناتا' => array( 'sonata', array() ), 'توسان' => array( 'tucson', array() ), 'سانتافه' => array( 'santa-fe', array() ) ) ),
	'کیا'       => array( 'kia', 'kr', array( 'سراتو' => array( 'cerato', array() ), 'اسپورتیج' => array( 'sportage', array() ), 'سورنتو' => array( 'sorento', array() ), 'اپتیما' => array( 'optima', array() ) ) ),
	'سانگ‌یانگ' => array( 'ssangyong', 'kr', array( 'تیوولی' => array( 'tivoli', array() ), 'کوراندو' => array( 'korando', array() ) ) ),
);

$v = array();
foreach ( $makes as $make => $def ) {
	$make_id = $yadak_demo_term( array( $make ), Yadak_Fitment::TAXONOMY );
	wp_update_term( $make_id, Yadak_Fitment::TAXONOMY, array( 'slug' => $def[0] ) );
	update_term_meta( $make_id, 'yadak_origin', $def[1] );
	foreach ( $def[2] as $model => $mdef ) {
		$model_id = $yadak_demo_term( array( $make, $model ), Yadak_Fitment::TAXONOMY );
		wp_update_term( $model_id, Yadak_Fitment::TAXONOMY, array( 'slug' => $mdef[0] ) );
		$v[ $mdef[0] ] = $model_id;
		foreach ( $mdef[1] as $variant ) {
			$v[ $mdef[0] . '/' . sanitize_title( $variant ) ] = $yadak_demo_term( array( $make, $model, $variant ), Yadak_Fitment::TAXONOMY );
		}
	}
}

$cats      = array();
$cat_slugs = array(
	'چراغ'               => array( 'lights', '' ),
	'چراغ جلو'           => array( 'headlights', 'چراغ' ),
	'چراغ عقب (خطر)'     => array( 'taillights', 'چراغ' ),
	'مه‌شکن و راهنما'     => array( 'fog-lights', 'چراغ' ),
	'لامپ و LED'         => array( 'bulbs', 'چراغ' ),
	'سپر و بدنه'         => array( 'bumpers', '' ),
	'سپر جلو'            => array( 'front-bumper', 'سپر و بدنه' ),
	'سپر عقب'            => array( 'rear-bumper', 'سپر و بدنه' ),
	'جلوپنجره'           => array( 'grille', 'سپر و بدنه' ),
	'آینه بغل'           => array( 'mirrors', 'سپر و بدنه' ),
	'اکسسوری'            => array( 'accessories', '' ),
	'کفپوش و روکش'       => array( 'mats-covers', 'اکسسوری' ),
	'باربند و رکاب'      => array( 'racks-steps', 'اکسسوری' ),
	'سنسور و دوربین'     => array( 'sensors-cameras', 'اکسسوری' ),
);
foreach ( $cat_slugs as $name => $def ) {
	$path          = $def[1] ? array( $def[1], $name ) : array( $name );
	$cats[ $name ] = $yadak_demo_term( $path, 'product_cat' );
	wp_update_term( $cats[ $name ], 'product_cat', array( 'slug' => $def[0] ) );
}
$brand = static function ( $name ) use ( $yadak_demo_term ) {
	return taxonomy_exists( 'product_brand' ) ? $yadak_demo_term( array( $name ), 'product_brand' ) : 0;
};

// name, category, brand, part no, OEM, alt names, quality, price, b2b, dealer, cost, stock, replace days, fits.
$products = array(
	array( 'چراغ جلو راست چری تیگو ۷', 'چراغ جلو', 'نمونه-الف', 'DEMO-HL-TG7R', 'DEMO-OEM-T15-4421020', 'چراغ جلو تیگو, headlight tiggo 7, cheragh', 'oem', 18500000, 17200000, 16500000, 14000000, 6, 0, array( 'tiggo-7' ) ),
	array( 'چراغ جلو چپ چری تیگو ۷', 'چراغ جلو', 'نمونه-الف', 'DEMO-HL-TG7L', 'DEMO-OEM-T15-4421010', 'چراغ جلو تیگو, headlight tiggo 7', 'oem', 18500000, 17200000, 16500000, 14000000, 4, 0, array( 'tiggo-7' ) ),
	array( 'چراغ جلو راست کیا اسپورتیج', 'چراغ جلو', 'نمونه-ب', 'DEMO-HL-SPR', 'DEMO-OEM-92102-3W010', 'چراغ جلو اسپورتیج, headlight sportage', 'aftermarket', 14500000, 13600000, 13000000, 11000000, 3, 0, array( 'sportage' ) ),
	array( 'چراغ جلو تویوتا کرولا (جفت، LED)', 'چراغ جلو', 'نمونه-ب', 'DEMO-HL-CRL', 'DEMO-OEM-81145-02K30', 'چراغ جلو کرولا, headlight corolla led', 'aftermarket', 39000000, 36500000, 35000000, 30000000, 2, 0, array( 'corolla' ) ),
	array( 'چراغ خطر عقب چپ هیوندای توسان', 'چراغ عقب (خطر)', 'نمونه-ب', 'DEMO-TL-TSNL', 'DEMO-OEM-92401-D3100', 'چراغ خطر, taillight tucson, stop', 'oem', 11200000, 10400000, 9900000, 8300000, 5, 0, array( 'tucson' ) ),
	array( 'چراغ خطر عقب راست ام‌وی‌ام X22', 'چراغ عقب (خطر)', 'نمونه-الف', 'DEMO-TL-X22R', 'DEMO-OEM-J52-4133020', 'چراغ خطر, taillight mvm', 'oem', 4900000, 4500000, 4300000, 3600000, 9, 0, array( 'x22' ) ),
	array( 'مه‌شکن جلو نیسان قشقایی (جفت)', 'مه‌شکن و راهنما', 'نمونه-ج', 'DEMO-FG-QQ', 'DEMO-OEM-26150-8990B', 'مه شکن, fog light, meh shekan', 'aftermarket', 6300000, 5800000, 5500000, 4500000, 7, 0, array( 'qashqai' ) ),
	array( 'لامپ LED هدلایت H7 (جفت)', 'لامپ و LED', 'نمونه-ج', 'DEMO-LED-H7', '', 'لامپ ال ای دی, led h7, lamp', 'aftermarket', 2900000, 2600000, 2450000, 1800000, 60, 730, array() ),
	array( 'سپر جلو هیوندای النترا', 'سپر جلو', 'نمونه-د', 'DEMO-FB-ELN', 'DEMO-OEM-86511-F2000', 'سپر جلو النترا, front bumper elantra, separ', 'aftermarket', 12800000, 11900000, 11400000, 9500000, 4, 0, array( 'elantra' ) ),
	array( 'سپر جلو ام‌وی‌ام X33 S', 'سپر جلو', 'نمونه-د', 'DEMO-FB-X33', 'DEMO-OEM-S21-2803111', 'سپر جلو, front bumper mvm', 'oem', 9700000, 9000000, 8600000, 7200000, 5, 0, array( 'x33/x33-s' ) ),
	array( 'سپر عقب کیا سراتو', 'سپر عقب', 'نمونه-د', 'DEMO-RB-CRT', 'DEMO-OEM-86611-A7000', 'سپر عقب سراتو, rear bumper cerato', 'aftermarket', 11600000, 10800000, 10300000, 8600000, 3, 0, array( 'cerato' ) ),
	array( 'جلوپنجره هاوال H6', 'جلوپنجره', 'نمونه-ه', 'DEMO-GR-H6', 'DEMO-OEM-8401101XKZ16A', 'جلو پنجره, grille haval', 'oem', 15400000, 14300000, 13700000, 11500000, 2, 0, array( 'h6' ) ),
	array( 'آینه بغل راست تویوتا کمری (برقی)', 'آینه بغل', 'نمونه-ه', 'DEMO-MR-CMR', 'DEMO-OEM-87910-06B40', 'آینه بغل, side mirror camry', 'aftermarket', 13200000, 12300000, 11800000, 9800000, 3, 0, array( 'camry' ) ),
	array( 'کفپوش سه‌بعدی چری تیگو ۸ پرو', 'کفپوش و روکش', 'نمونه-و', 'DEMO-MT-TG8', '', 'کفپوش, کف پوش, floor mat, 3d mat', 'aftermarket', 4200000, 3800000, 3600000, 2700000, 20, 0, array( 'tiggo-8-pro' ) ),
	array( 'باربند سقف تویوتا پرادو', 'باربند و رکاب', 'نمونه-و', 'DEMO-RR-PRD', '', 'باربند, roof rack, bar band', 'aftermarket', 8900000, 8200000, 7900000, 6400000, 5, 0, array( 'prado' ) ),
	array( 'رکاب جانبی کیا سورنتو (جفت)', 'باربند و رکاب', 'نمونه-و', 'DEMO-SS-SRN', '', 'رکاب, side step, rekab', 'aftermarket', 16500000, 15300000, 14700000, 12200000, 3, 0, array( 'sorento' ) ),
	array( 'سنسور دنده عقب ۴ چشم با نمایشگر', 'سنسور و دوربین', 'نمونه-ز', 'DEMO-PS-4', '', 'سنسور پارک, parking sensor', 'aftermarket', 2400000, 2150000, 2000000, 1500000, 35, 0, array() ),
	array( 'دوربین دنده عقب هیوندای سوناتا', 'سنسور و دوربین', 'نمونه-ز', 'DEMO-RC-SNT', 'DEMO-OEM-95760-C1000', 'دوربین عقب, rear camera', 'oem', 7800000, 7200000, 6900000, 5700000, 6, 0, array( 'sonata' ) ),
);

$count = 0;
foreach ( $products as $p ) {
	list( $name, $cat, $brand_name, $pn, $oem, $alt, $quality, $price, $b2b, $dealer, $cost, $stock, $replace, $fits ) = $p;
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
	$product->update_meta_data( '_yadak_cost', (string) $cost );
	$product->update_meta_data( '_yadak_replace_days', $replace ? (string) $replace : '' );
	$id = $product->save();
	wp_set_object_terms( $id, array_values( array_intersect_key( $v, array_flip( $fits ) ) ), Yadak_Fitment::TAXONOMY );
	if ( $brand( $brand_name ) ) {
		wp_set_object_terms( $id, array( $brand( $brand_name ) ), 'product_brand' );
	}
	Yadak_Search::index( $id );
	++$count;
}

echo "Demo data ready: {$count} products.\n";
