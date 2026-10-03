<?php

namespace Database\Seeders;

use App\Models\CarMake;
use App\Models\Customer;
use App\Models\FitmentRequest;
use App\Models\Order;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data only. Part numbers are prefixed DEMO- and are not real catalogue numbers.
 * Staff logins: manager@ / sales@ / tech@ / ship@ghatefori.ir — password: password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::create(['name' => 'مهدی (مدیر)', 'email' => 'manager@ghatefori.ir', 'role' => 'manager', 'password' => 'password']);
        User::create(['name' => 'کارشناس فروش', 'email' => 'sales@ghatefori.ir', 'role' => 'sales', 'password' => 'password']);
        $tech = User::create(['name' => 'کارشناس فنی', 'email' => 'tech@ghatefori.ir', 'role' => 'technical', 'password' => 'password']);
        User::create(['name' => 'مسئول ارسال', 'email' => 'ship@ghatefori.ir', 'role' => 'shipping', 'password' => 'password']);

        $fleet = [
            ['چری', 'chery', 'chinese', [['تیگو ۷', 1398, 1403, '1.5 توربو', 'DCT'], ['تیگو ۸ پرو', 1401, 1403, '1.6 توربو', 'DCT'], ['آریزو ۵', 1396, 1401, '1.5', 'CVT']]],
            ['جک', 'jac', 'chinese', [['S5', 1395, 1401, '1.5 توربو', 'دستی'], ['J4', 1397, 1402, '1.5', 'CVT']]],
            ['ام‌وی‌ام', 'mvm', 'chinese', [['X22', 1396, 1402, '1.5', 'دستی'], ['X33 کراس', 1398, 1402, '1.5', 'CVT']]],
            ['هیوندای', 'hyundai', 'korean', [['النترا', 1393, 1397, '2.0', 'اتومات'], ['توسان', 1394, 1397, '2.4', 'اتومات'], ['سوناتا', 1393, 1396, '2.4', 'اتومات']]],
            ['کیا', 'kia', 'korean', [['سراتو', 1393, 1397, '2.0', 'اتومات'], ['اسپورتیج', 1394, 1397, '2.4', 'اتومات']]],
            ['تویوتا', 'toyota', 'japanese', [['کرولا', 1392, 1397, '1.8', 'CVT'], ['کمری', 1392, 1396, '2.5', 'اتومات']]],
            ['نیسان', 'nissan', 'japanese', [['قشقایی', 1393, 1397, '2.0', 'CVT']]],
        ];
        $v = [];
        foreach ($fleet as $i => [$name, $slug, $origin, $models]) {
            $make = CarMake::create(compact('name', 'slug', 'origin') + ['sort' => $i]);
            foreach ($models as [$model, $from, $to, $engine, $gearbox]) {
                $v["$slug/$model"] = $make->vehicles()->create(['model' => $model, 'year_from' => $from, 'year_to' => $to, 'engine' => $engine, 'gearbox' => $gearbox])->id;
            }
        }

        $cats = collect([
            ['ترمز', 'brakes', '🛑'], ['فیلتر و سرویس', 'service', '🧰'], ['تعلیق و جلوبندی', 'suspension', '🔩'],
            ['برقی و سنسور', 'electrical', '⚡'], ['چراغ', 'lights', '💡'], ['بدنه و سپر', 'body', '🚘'], ['موتور', 'engine', '⚙'],
        ])->mapWithKeys(fn ($c, $i) => [$c[1] => PartCategory::create(['name' => $c[0], 'slug' => $c[1], 'icon' => $c[2], 'sort' => $i])->id]);

        $brand = collect([
            ['جنیون چری', 'چین', 'بسته‌بندی رسمی خودروساز.'],
            ['موبیس (Mobis)', 'کره جنوبی', 'تأمین‌کننده قطعات خط تولید هیوندای و کیا.'],
            ['تویوتا جنیون', 'ژاپن', 'بسته‌بندی رسمی تویوتا.'],
            ['سانگشین (Sangsin)', 'کره جنوبی', 'لنت یدکی؛ نسبت به جنیون قیمت پایین‌تر و گرد ترمز کمی بیشتر.'],
            ['بوش (Bosch)', 'آلمان (تولید در کشورهای مختلف)', 'کشور سازنده روی بسته هر کالا جداگانه درج می‌شود.'],
            ['NGK', 'ژاپن', 'شمع و سنسور اکسیژن.'],
            ['TYC', 'تایوان', 'چراغ یدکی؛ هم‌اندازه نمونه اصلی، رنگ طلق ممکن است کمی متفاوت باشد.'],
        ])->mapWithKeys(fn ($b) => [$b[0] => PartBrand::create(['name' => $b[0], 'country' => $b[1], 'notes' => $b[2]])->id]);

        $bazar = Supplier::create(['name' => 'بنکداری امین (بازار چراغ برق)', 'phone' => '021-11111111', 'payment_terms' => 'نقد', 'return_terms' => 'قطعات برقی پس از نصب مرجوع نمی‌شود؛ قطعات مکانیکی تا ۷ روز با بسته‌بندی سالم.']);
        $koreaParts = Supplier::create(['name' => 'پخش قطعات کره‌پارت', 'phone' => '021-22222222', 'payment_terms' => 'چک ۱۵ روزه', 'return_terms' => 'تعویض خرابی تأییدشده تا ۳ ماه.', 'ships_direct' => true]);
        $nasr = Supplier::create(['name' => 'فروشگاه نصر (لوازم چینی)', 'phone' => '021-33333333', 'payment_terms' => 'نقد', 'return_terms' => 'فقط کالای معیوب، تا ۴۸ ساعت.']);

        $catalog = [
            // name, cat, brand, pn, auth, side, pos, stock, qty, lead, price, cost(s), vehicles, fitment-check, incompat
            ['لنت ترمز جلو', 'brakes', 'موبیس (Mobis)', 'DEMO-58101-2S', 'oem_supplier', null, 'front', 'in_stock', 6, null, 2_450_000, [[$koreaParts, 1_900_000], [$bazar, 1_980_000]], ['hyundai/توسان', 'kia/اسپورتیج'], false, null],
            ['لنت ترمز جلو', 'brakes', 'سانگشین (Sangsin)', 'DEMO-SP1401', 'aftermarket', null, 'front', 'in_stock', 10, null, 1_650_000, [[$bazar, 1_250_000], [$koreaParts, 1_300_000]], ['hyundai/توسان', 'kia/اسپورتیج'], false, null],
            ['لنت ترمز جلو', 'brakes', 'جنیون چری', 'DEMO-T21-3501080', 'genuine', null, 'front', 'on_request', 0, 2, 2_100_000, [[$nasr, 1_650_000]], ['chery/تیگو ۷'], true, 'نسخه دارای ترمز دستی برقی (EPB) کالیپر متفاوت دارد؛ شماره فنی قطعه قبلی را بفرستید.'],
            ['فیلتر روغن', 'service', 'جنیون چری', 'DEMO-E4G15-1012010', 'genuine', null, null, 'in_stock', 25, null, 380_000, [[$nasr, 260_000], [$bazar, 275_000]], ['chery/تیگو ۷', 'chery/تیگو ۸ پرو', 'chery/آریزو ۵'], false, null],
            ['فیلتر هوای موتور', 'service', 'موبیس (Mobis)', 'DEMO-28113-2H', 'oem_supplier', null, null, 'in_stock', 12, null, 690_000, [[$koreaParts, 520_000]], ['hyundai/النترا', 'kia/سراتو'], false, null],
            ['شمع موتور (۴ عدد)', 'engine', 'NGK', 'DEMO-ILKAR7B11', 'aftermarket', null, null, 'on_request', 0, 1, 3_200_000, [[$bazar, 2_600_000], [$koreaParts, 2_700_000]], ['hyundai/سوناتا', 'kia/اسپورتیج', 'hyundai/توسان'], false, null],
            ['سنسور اکسیژن (بالا)', 'electrical', 'بوش (Bosch)', 'DEMO-0258017', 'aftermarket', null, null, 'on_request', 0, 3, 4_800_000, [[$bazar, 3_900_000]], ['toyota/کرولا'], true, 'نسخه‌های قبل و بعد از سال ۱۳۹۵ سوکت متفاوت دارند — عکس سوکت لازم است.'],
            ['چراغ جلو', 'lights', 'TYC', 'DEMO-20-B157', 'aftermarket', 'left', 'front', 'out_of_stock', 0, null, 9_500_000, [[$bazar, 7_800_000]], ['toyota/کرولا'], true, 'برای نسخه دارای چراغ LED کارخانه مناسب نیست.'],
            ['کمک فنر جلو', 'suspension', 'جنیون چری', 'DEMO-T15-2905010', 'genuine', 'right', 'front', 'on_request', 0, 3, 6_900_000, [[$nasr, 5_600_000]], ['chery/تیگو ۷'], true, null],
            ['دیسک ترمز جلو', 'brakes', 'تویوتا جنیون', 'DEMO-43512-02', 'genuine', null, 'front', 'in_stock', 2, null, 7_400_000, [[$koreaParts, 6_100_000]], ['toyota/کرولا'], false, null],
            ['سپر جلو (رنگ‌نشده)', 'body', 'جنیون چری', 'DEMO-J69-2803011', 'genuine', null, 'front', 'on_request', 0, 5, 14_500_000, [[$nasr, 12_200_000]], ['chery/آریزو ۵'], true, 'مدل‌های اسپرت (Sport) سپر متفاوت دارند.'],
            ['فیلتر کابین', 'service', 'موبیس (Mobis)', 'DEMO-97133-D1', 'oem_supplier', null, null, 'in_stock', 15, null, 520_000, [[$koreaParts, 360_000], [$bazar, 390_000]], ['hyundai/توسان', 'kia/اسپورتیج', 'hyundai/سوناتا'], false, null],
        ];

        $products = [];
        foreach ($catalog as [$name, $cat, $b, $pn, $auth, $side, $pos, $stock, $qty, $lead, $price, $offers, $fits, $check, $incompat]) {
            $full = $name.' '.$b;
            $p = Product::create([
                'part_category_id' => $cats[$cat], 'part_brand_id' => $brand[$b], 'name' => $name, 'slug' => Product::uniqueSlug($full),
                'part_number' => $pn, 'authenticity' => $auth, 'condition' => 'new', 'side' => $side, 'position' => $pos,
                'stock_status' => $stock, 'stock_qty' => $qty, 'lead_time_days' => $lead, 'price' => $price,
                'requires_fitment_check' => $check, 'incompatibility_notes' => $incompat, 'price_checked_at' => now()->subDays(random_int(0, 9)),
                'warranty' => $auth === 'genuine' ? '۶ ماه ضمانت سلامت از طرف تأمین‌کننده' : '۳ ماه ضمانت سلامت از طرف تأمین‌کننده',
                'return_policy' => $cat === 'electrical' ? 'پس از نصب فقط در صورت خرابی تأییدشده' : 'تا ۷ روز، نصب‌نشده و با بسته‌بندی سالم',
                'package_contents' => str_contains($name, 'لنت') ? 'یک دست (۴ عدد)' : '۱ عدد',
                'description' => "داده نمونه برای نمایش امکانات سایت. شماره فنی با پیشوند DEMO واقعی نیست.\nپیش از ارسال، کد روی بسته با سفارش تطبیق داده می‌شود.",
            ]);
            foreach ($offers as [$supplier, $cost]) {
                $p->offers()->create(['supplier_id' => $supplier->id, 'cost_price' => $cost, 'stock_qty' => random_int(0, 20), 'lead_time_days' => random_int(0, 3), 'valid_until' => today()->addDays(random_int(-2, 5))]);
            }
            $p->vehicles()->sync(collect($fits)->map(fn ($k) => $v[$k])->all());
            $products[] = $p;
        }

        // A few customers, orders and requests so every screen has something on it.
        $ali = Customer::create(['name' => 'علی محمدی', 'phone' => '09121111111', 'type' => 'owner', 'city' => 'تهران', 'source' => 'instagram', 'marketing_consent' => true]);
        $garage = Customer::create(['name' => 'تعمیرگاه برادران کریمی', 'phone' => '09122222222', 'type' => 'garage', 'city' => 'تهران', 'source' => 'garage', 'marketing_consent' => true]);
        $aliCar = $ali->vehicles()->create(['vehicle_id' => $v['hyundai/توسان'], 'year' => 1395, 'last_service_date' => today()->subMonths(5), 'last_service_km' => 92000]);

        $delivered = Order::create(['customer_id' => $ali->id, 'customer_vehicle_id' => $aliCar->id, 'status' => 'delivered', 'city' => 'تهران', 'address' => 'تهران، نمونه',
            'shipping_charge' => 120_000, 'shipping_cost' => 110_000, 'packaging_cost' => 25_000, 'payment_fee' => 25_000, 'return_reserve' => 50_000, 'acquisition_cost' => 150_000,
            'fitment_checked_by' => $tech->id, 'fitment_note' => 'مدل و سال با فهرست سازگاری تطبیق داده شد.', 'promised_at' => now()->subDays(6), 'confirmed_at' => now()->subDays(7),
            'shipped_at' => now()->subDays(6), 'delivered_at' => now()->subDays(5), 'tracking_code' => 'TPX-0001', 'source' => 'instagram', 'created_at' => now()->subDays(7)]);
        $delivered->items()->create(['product_id' => $products[0]->id, 'qty' => 1, 'unit_price' => 2_450_000, 'unit_cost' => 1_900_000, 'supplier_id' => $koreaParts->id]);
        $delivered->items()->create(['product_id' => $products[11]->id, 'qty' => 1, 'unit_price' => 520_000, 'unit_cost' => 360_000, 'supplier_id' => $koreaParts->id]);
        $delivered->log('سفارش تحویل شد.', $manager);
        $delivered->review()->create(['rating' => 5, 'body' => 'قبل از خرید شماره شاسی را چک کردند و قطعه دقیق همان بود. یک روزه رسید.', 'publish_consent' => true, 'is_published' => true]);

        $pending = Order::create(['customer_id' => $garage->id, 'status' => 'awaiting_confirmation', 'city' => 'تهران', 'address' => 'تهران، خیابان نمونه، تعمیرگاه',
            'shipping_charge' => 120_000, 'shipping_cost' => 120_000, 'packaging_cost' => 25_000, 'payment_fee' => 45_000, 'return_reserve' => 90_000, 'source' => 'garage']);
        $pending->items()->create(['product_id' => $products[2]->id, 'qty' => 2, 'unit_price' => 2_100_000, 'unit_cost' => 1_650_000, 'supplier_id' => $nasr->id]);
        $pending->log('سفارش توسط مشتری ثبت شد.');

        $ali->reminders()->create(['customer_vehicle_id' => $aliCar->id, 'part_category_id' => $cats['service'], 'title' => 'پرسیدن کارکرد و وضعیت فیلتر روغن', 'due_on' => today()->addDays(3), 'is_estimate' => true, 'basis' => 'تاریخ آخرین سرویس اعلام‌شده توسط مشتری؛ کیلومتر فعلی نامعلوم']);

        FitmentRequest::create(['name' => 'رضا احمدی', 'phone' => '09123333333', 'customer_id' => Customer::capture(['name' => 'رضا احمدی', 'phone' => '09123333333'])->id,
            'vehicle_id' => $v['chery/تیگو ۷'], 'year' => 1400, 'engine' => '1.5 توربو', 'description' => 'لنت عقب، ماشین ترمز دستی برقی دارد', 'product_id' => null]);
        FitmentRequest::create(['name' => 'سارا نوری', 'phone' => '09124444444', 'vehicle_text' => 'هاوال H6', 'year' => 1399, 'description' => 'آینه بغل سمت راننده با راهنما']);

        $products[7]->alerts()->create(['name' => 'حمید', 'phone' => '09125555555']);
    }
}
