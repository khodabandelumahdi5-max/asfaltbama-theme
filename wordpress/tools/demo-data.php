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
	'ایران‌خودرو' => array( 'ikco', 'ir', array( 'پژو ۲۰۶' => array( 'peugeot-206', array( 'تیپ ۲', 'تیپ ۵' ) ), 'پژو پارس' => array( 'pars', array() ), 'سمند' => array( 'samand', array() ), 'دنا' => array( 'dena', array() ) ) ),
	'سایپا'     => array( 'saipa', 'ir', array( 'پراید' => array( 'pride', array() ), 'تیبا' => array( 'tiba', array() ), 'شاهین' => array( 'shahin', array() ) ) ),
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
			$v[ $mdef[0] . '/' . $variant ] = $yadak_demo_term( array( $make, $model, $variant ), Yadak_Fitment::TAXONOMY );
		}
	}
}

$cats      = array();
// name => [slug, parent name].
$cat_slugs = array(
	'برق و الکترونیک خودرو' => array( 'electrical', '' ),
	'ECU و ماژول‌ها'        => array( 'ecu', 'برق و الکترونیک خودرو' ),
	'سنسورها'               => array( 'sensors', 'برق و الکترونیک خودرو' ),
	'دینام و استارت'        => array( 'alternator-starter', 'برق و الکترونیک خودرو' ),
	'سیم‌کشی و فیوز'        => array( 'wiring', 'برق و الکترونیک خودرو' ),
	'بدنه، سقف و چراغ'      => array( 'body-lights', '' ),
	'چراغ جلو'              => array( 'headlights', 'بدنه، سقف و چراغ' ),
	'چراغ عقب (خطر)'        => array( 'taillights', 'بدنه، سقف و چراغ' ),
	'مه‌شکن و پروژکتور'      => array( 'fog-lights', 'بدنه، سقف و چراغ' ),
	'قطعات بدنه'            => array( 'body-parts', 'بدنه، سقف و چراغ' ),
	'سقف و باربند'          => array( 'roof', 'بدنه، سقف و چراغ' ),
	'عمومی و مکانیکی'       => array( 'mechanical', '' ),
	'لوازم مصرفی'           => array( 'consumables', 'عمومی و مکانیکی' ),
	'جلوبندی و تعلیق'       => array( 'suspension', 'عمومی و مکانیکی' ),
	'ترمز'                  => array( 'brake', 'عمومی و مکانیکی' ),
	'قطعات موتور'           => array( 'engine', 'عمومی و مکانیکی' ),
);
foreach ( $cat_slugs as $name => $def ) {
	$path          = $def[1] ? array( $def[1], $name ) : array( $name );
	$cats[ $name ] = $yadak_demo_term( $path, 'product_cat' );
	wp_update_term( $cats[ $name ], 'product_cat', array( 'slug' => $def[0] ) );
}
$brand = static function ( $name ) use ( $yadak_demo_term ) {
	return taxonomy_exists( 'product_brand' ) ? $yadak_demo_term( array( $name ), 'product_brand' ) : 0;
};

// name, category, brand, part no, OEM, alt names, quality, price, b2b, dealer, cost, stock, replace days, fits, [year from, year to].
$products = array(
	// Electrical.
	array( 'ECU (کامپیوتر موتور) پژو ۲۰۶ تیپ ۵', 'ECU و ماژول‌ها', 'نمونه-ط', 'DEMO-ECU-206', 'DEMO-OEM-9665674480', 'ای سی یو, ecu, کامپیوتر ماشین, ایسیو', 'genuine', 38500000, 36000000, 34500000, 29000000, 3, 0, array( 'peugeot-206/تیپ ۵' ), array( 2008, 2023 ) ),
	array( 'ماژول ECU چری تیگو ۷', 'ECU و ماژول‌ها', 'نمونه-ط', 'DEMO-ECU-TG7', 'DEMO-OEM-T15-3605010', 'ecu tiggo, کامپیوتر موتور', 'oem', 64000000, 60500000, 58000000, 49000000, 1, 0, array( 'tiggo-7' ), array( 2019, 2025 ) ),
	array( 'سنسور اکسیژن هیوندای توسان', 'سنسورها', 'نمونه-ه', 'DEMO-O2-TSN', 'DEMO-OEM-39210-2G100', 'سنسور اکسیژن, oxygen sensor, lambda', 'oem', 4800000, 4400000, 4200000, 3500000, 7, 0, array( 'tucson' ), array( 2015, 2021 ) ),
	array( 'سنسور ABS چرخ جلو کیا سراتو', 'سنسورها', 'نمونه-ه', 'DEMO-ABS-CRT', 'DEMO-OEM-95670-A7000', 'سنسور ای بی اس, abs sensor', 'aftermarket', 2900000, 2650000, 2500000, 1950000, 9, 0, array( 'cerato' ), array( 2013, 2018 ) ),
	array( 'سنسور دور موتور (میل‌لنگ) سمند و پژو', 'سنسورها', 'نمونه-ه', 'DEMO-CKP-IK', 'DEMO-OEM-1920-AW', 'سنسور میل لنگ, crank sensor', 'aftermarket', 850000, 760000, 720000, 520000, 40, 0, array( 'samand', 'pars', 'peugeot-206' ) ),
	array( 'دینام تویوتا کرولا', 'دینام و استارت', 'نمونه-ی', 'DEMO-ALT-CRL', 'DEMO-OEM-27060-0T040', 'دینام, آلترناتور, alternator', 'genuine', 27500000, 25800000, 24900000, 21000000, 2, 0, array( 'corolla' ), array( 2014, 2019 ) ),
	array( 'استارت کامل ام‌وی‌ام X33', 'دینام و استارت', 'نمونه-ی', 'DEMO-STR-X33', 'DEMO-OEM-S21-3708110', 'استارتر, starter motor', 'oem', 12900000, 11900000, 11400000, 9400000, 4, 0, array( 'x33' ) ),
	array( 'دسته سیم موتور پراید', 'سیم‌کشی و فیوز', 'نمونه-ی', 'DEMO-WH-PRD', 'DEMO-OEM-S-3724100', 'سیم کشی, wiring harness, دسته سیم', 'aftermarket', 3600000, 3250000, 3100000, 2400000, 6, 0, array( 'pride', 'tiba' ) ),
	// Body, roof and lights.
	array( 'چراغ جلو راست چری تیگو ۷', 'چراغ جلو', 'نمونه-الف', 'DEMO-HL-TG7R', 'DEMO-OEM-T15-4421020', 'چراغ جلو تیگو, headlight tiggo 7, cheragh', 'oem', 18500000, 17200000, 16500000, 14000000, 6, 0, array( 'tiggo-7' ), array( 2019, 2025 ) ),
	array( 'چراغ جلو کیا اسپورتیج (راست)', 'چراغ جلو', 'نمونه-ب', 'DEMO-HL-SPR', 'DEMO-OEM-92102-3W010', 'چراغ جلو اسپورتیج, headlight sportage', 'aftermarket', 14500000, 13600000, 13000000, 11000000, 3, 0, array( 'sportage' ), array( 2011, 2015 ) ),
	array( 'چراغ جلو تویوتا کرولا (جفت، LED)', 'چراغ جلو', 'نمونه-ب', 'DEMO-HL-CRL', 'DEMO-OEM-81145-02K30', 'چراغ جلو کرولا, headlight corolla led', 'aftermarket', 39000000, 36500000, 35000000, 30000000, 2, 0, array( 'corolla' ), array( 2019, 2023 ) ),
	array( 'چراغ جلو دنا پلاس', 'چراغ جلو', 'نمونه-الف', 'DEMO-HL-DNA', 'DEMO-OEM-1561-HL', 'چراغ جلو دنا, headlight dena', 'oem', 9800000, 9100000, 8700000, 7200000, 5, 0, array( 'dena' ) ),
	array( 'چراغ خطر عقب چپ هیوندای توسان', 'چراغ عقب (خطر)', 'نمونه-ب', 'DEMO-TL-TSNL', 'DEMO-OEM-92401-D3100', 'چراغ خطر, taillight tucson, stop', 'oem', 11200000, 10400000, 9900000, 8300000, 5, 0, array( 'tucson' ), array( 2016, 2020 ) ),
	array( 'مه‌شکن پروژکتوری نیسان قشقایی (جفت)', 'مه‌شکن و پروژکتور', 'نمونه-ج', 'DEMO-FG-QQ', 'DEMO-OEM-26150-8990B', 'مه شکن, پروژکتور, fog light, projector', 'aftermarket', 6300000, 5800000, 5500000, 4500000, 7, 0, array( 'qashqai' ) ),
	array( 'سپر جلو هیوندای النترا', 'قطعات بدنه', 'نمونه-د', 'DEMO-FB-ELN', 'DEMO-OEM-86511-F2000', 'سپر جلو النترا, front bumper elantra, separ', 'aftermarket', 12800000, 11900000, 11400000, 9500000, 4, 0, array( 'elantra' ), array( 2016, 2018 ) ),
	array( 'سپر عقب کیا سراتو', 'قطعات بدنه', 'نمونه-د', 'DEMO-RB-CRT', 'DEMO-OEM-86611-A7000', 'سپر عقب سراتو, rear bumper cerato', 'aftermarket', 11600000, 10800000, 10300000, 8600000, 3, 0, array( 'cerato' ) ),
	array( 'جلوپنجره هاوال H6', 'قطعات بدنه', 'نمونه-ه', 'DEMO-GR-H6', 'DEMO-OEM-8401101XKZ16A', 'جلو پنجره, grille haval', 'oem', 15400000, 14300000, 13700000, 11500000, 2, 0, array( 'h6' ) ),
	array( 'آینه بغل راست تویوتا کمری (برقی)', 'قطعات بدنه', 'نمونه-ه', 'DEMO-MR-CMR', 'DEMO-OEM-87910-06B40', 'آینه بغل, side mirror camry', 'aftermarket', 13200000, 12300000, 11800000, 9800000, 3, 0, array( 'camry' ) ),
	array( 'باربند سقف تویوتا پرادو', 'سقف و باربند', 'نمونه-و', 'DEMO-RR-PRD', '', 'باربند, roof rack, bar band', 'aftermarket', 8900000, 8200000, 7900000, 6400000, 5, 0, array( 'prado' ) ),
	array( 'رودری سقف کیا سورنتو', 'سقف و باربند', 'نمونه-و', 'DEMO-RL-SRN', 'DEMO-OEM-87270-C5000', 'رودری سقف, roof lining', 'oem', 7600000, 7000000, 6700000, 5500000, 2, 0, array( 'sorento' ) ),
	// General and mechanical.
	array( 'لنت ترمز جلو هیوندای النترا و کیا سراتو', 'ترمز', 'نمونه-ب', 'DEMO-BP-HK01', "DEMO-OEM-58101-F2A00\nDEMO-XR-2251", 'لنت جلو النترا, lent cerato, brake pad', 'aftermarket', 2950000, 2700000, 2550000, 1950000, 45, 365, array( 'elantra', 'cerato' ) ),
	array( 'لنت ترمز جلو پژو ۲۰۶', 'ترمز', 'نمونه-ب', 'DEMO-BP-206', 'DEMO-OEM-4254-C1', 'لنت جلو, lent 206, brake pad', 'aftermarket', 1850000, 1650000, 1520000, 1150000, 60, 365, array( 'peugeot-206' ) ),
	array( 'فیلتر روغن هیوندای و کیا', 'لوازم مصرفی', 'نمونه-ج', 'DEMO-OF-HK26', "DEMO-OEM-26300-35505\nDEMO-XR-0331", 'فیلتر روغن, oil filter', 'genuine', 480000, 430000, 400000, 310000, 150, 180, array( 'elantra', 'sonata', 'tucson', 'santa-fe', 'cerato', 'sportage', 'sorento', 'optima' ) ),
	array( 'روغن موتور 5W-30 چهار لیتری', 'لوازم مصرفی', 'نمونه-ز', 'DEMO-OL-0530', '', 'روغن, oil, 5w30', 'genuine', 3400000, 3150000, 3000000, 2550000, 80, 180, array() ),
	array( 'کمک فنر جلو ام‌وی‌ام X33 (جفت)', 'جلوبندی و تعلیق', 'نمونه-د', 'DEMO-SA-X33', 'DEMO-OEM-S21-2905010', 'کمک جلو, komak mvm', 'oem', 8200000, 7600000, 7300000, 6100000, 6, 0, array( 'x33' ) ),
	array( 'شمع موتور هیوندای و کیا (بسته ۴ عددی)', 'قطعات موتور', 'نمونه-و', 'DEMO-SP-HK4', 'DEMO-OEM-18855-10060', 'شمع, spark plug, sham', 'genuine', 2600000, 2380000, 2250000, 1800000, 40, 730, array( 'elantra', 'cerato', 'tucson', 'sportage' ) ),
);

$count = 0;
foreach ( $products as $p ) {
	list( $name, $cat, $brand_name, $pn, $oem, $alt, $quality, $price, $b2b, $dealer, $cost, $stock, $replace, $fits ) = $p;
	$years = isset( $p[14] ) ? $p[14] : array( '', '' );
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
	$product->update_meta_data( '_yadak_year_from', (string) $years[0] );
	$product->update_meta_data( '_yadak_year_to', (string) $years[1] );
	$id = $product->save();
	wp_set_object_terms( $id, array_values( array_intersect_key( $v, array_flip( $fits ) ) ), Yadak_Fitment::TAXONOMY );
	if ( $brand( $brand_name ) ) {
		wp_set_object_terms( $id, array( $brand( $brand_name ) ), 'product_brand' );
	}
	Yadak_Search::index( $id );
	++$count;
}

echo "Demo data ready: {$count} products.\n";
