<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\CrmContact;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data. Log in with admin@ / supplier@ / buyer@asfaltbama.ir — password: password
     */
    public function run(): void
    {
        User::create(['name' => 'مدیر سایت', 'email' => 'admin@asfaltbama.ir', 'phone' => '09120000000', 'role' => 'admin', 'password' => 'password']);

        $categories = collect([
            ['قیر', 'bitumen', '🛢', ['قیر ۶۰/۷۰' => 'bitumen-6070', 'قیر ۸۵/۱۰۰' => 'bitumen-85100', 'قیر پلیمری' => 'polymer-bitumen']],
            ['آسفالت', 'asphalt', '🛣', ['آسفالت گرم' => 'hot-mix', 'آسفالت سرد' => 'cold-mix']],
            ['امولسیون و محلول', 'emulsion', '🧪', []],
            ['مصالح و سنگدانه', 'aggregates', '🪨', []],
            ['ماشین‌آلات راهسازی', 'machinery', '🚜', []],
            ['خدمات آسفالت‌کاری', 'services', '👷', []],
        ])->mapWithKeys(function ($row, $i) {
            [$name, $slug, $icon, $children] = $row;
            $parent = Category::create(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'sort' => $i]);
            $j = 0;
            foreach ($children as $childName => $childSlug) {
                Category::create(['name' => $childName, 'slug' => $childSlug, 'icon' => $icon, 'parent_id' => $parent->id, 'sort' => $j++]);
            }

            return [$slug => $parent];
        });
        $cat = fn (string $slug) => Category::where('slug', $slug)->value('id');

        $suppliers = [
            ['نفت و قیر پارس', 'بندرعباس', true, 'supplier@asfaltbama.ir', 'تولیدکننده و صادرکننده قیرهای عملکردی و پالایشگاهی با ظرفیت ماهانه ۵۰ هزار تن.'],
            ['آسفالت البرز', 'کرج', true, null, 'تولید آسفالت گرم با دو کارخانه و ناوگان حمل اختصاصی در استان البرز و تهران.'],
            ['راهسازان اصفهان', 'اصفهان', true, null, 'پیمانکار رتبه یک راه و باند؛ اجرای آسفالت معابر، پارکینگ و جاده.'],
            ['امولسیون ایرانیان', 'تهران', false, null, 'تولید امولسیون قیر کاتیونیک و آنیونیک، محلول قیر MC و RC.'],
            ['سنگ‌شکن توس', 'مشهد', true, null, 'تأمین شن، ماسه و فیلر استاندارد برای کارخانجات آسفالت.'],
        ];

        $companies = [];
        foreach ($suppliers as $i => [$name, $city, $verified, $email, $desc]) {
            $owner = User::create([
                'name' => 'مدیر '.$name,
                'email' => $email ?? 'supplier'.($i + 1).'@asfaltbama.ir',
                'phone' => '0912'.str_pad((string) ($i + 1), 7, '0', STR_PAD_LEFT),
                'role' => 'supplier',
                'password' => 'password',
            ]);
            $companies[] = $owner->company()->create([
                'name' => $name, 'slug' => Company::uniqueSlug($name), 'city' => $city, 'is_verified' => $verified,
                'description' => $desc, 'phone' => $owner->phone, 'founded_year' => 1380 + $i * 3,
            ]);
        }

        $products = [
            [0, 'bitumen-6070', 'قیر ۶۰/۷۰ فله پالایشگاهی', 'تن', 20, 18_500_000, 19_200_000],
            [0, 'bitumen-6070', 'قیر ۶۰/۷۰ بشکه‌ای ۱۸۰ کیلویی', 'بشکه', 100, 3_600_000, 3_800_000],
            [0, 'bitumen-85100', 'قیر ۸۵/۱۰۰ مناسب مناطق سردسیر', 'تن', 20, 18_900_000, null],
            [0, 'polymer-bitumen', 'قیر پلیمری PG 70-10', 'تن', 10, 27_000_000, 29_000_000],
            [1, 'hot-mix', 'آسفالت گرم توپکا (۰-۱۲)', 'تن', 30, 2_150_000, 2_300_000],
            [1, 'hot-mix', 'آسفالت گرم بیندر (۰-۱۹)', 'تن', 30, 1_950_000, 2_100_000],
            [1, 'cold-mix', 'آسفالت سرد کیسه‌ای ترمیمی', 'کیلوگرم', 500, 38_000, null],
            [2, 'services', 'اجرای آسفالت معابر و پارکینگ', 'متر مربع', 500, 420_000, 650_000],
            [2, 'services', 'لکه‌گیری و ترمیم آسفالت', 'متر مربع', 100, 380_000, null],
            [2, 'machinery', 'اجاره فینیشر و غلتک با اپراتور', 'سرویس', 1, null, null],
            [3, 'emulsion', 'امولسیون قیر کاتیونیک CSS-1h', 'تن', 5, 21_000_000, null],
            [3, 'emulsion', 'محلول قیر MC-250 (پریمکت)', 'بشکه', 20, 4_100_000, 4_300_000],
            [4, 'aggregates', 'شن شکسته ۱۲-۶ مخصوص آسفالت', 'تن', 100, 420_000, 480_000],
            [4, 'aggregates', 'ماسه شکسته شسته ۰-۶', 'تن', 100, 380_000, null],
            [4, 'aggregates', 'پودر سنگ (فیلر) آهکی', 'تن', 50, 650_000, null],
        ];
        foreach ($products as [$c, $slug, $name, $unit, $min, $pmin, $pmax]) {
            $companies[$c]->products()->create([
                'category_id' => $cat($slug), 'name' => $name, 'slug' => Product::uniqueSlug($name), 'unit' => $unit,
                'min_order' => $min, 'price_min' => $pmin, 'price_max' => $pmax, 'views' => random_int(20, 900),
                'description' => "{$name}\n\nتحویل در محل پروژه یا درب کارخانه. امکان ارائه برگه آنالیز و فاکتور رسمی.\nبرای سفارش‌های بالای حداقل، قیمت ویژه اعلام می‌شود.",
            ]);
        }

        $buyer = User::create(['name' => 'علی رضایی', 'email' => 'buyer@asfaltbama.ir', 'phone' => '09350000001', 'role' => 'buyer', 'password' => 'password']);

        $rfqs = [
            [$buyer->id, 'bitumen-6070', 'خرید ۲۰۰ تن قیر ۶۰/۷۰ برای پروژه کمربندی', 200, 'تن', 'شیراز', 'علی رضایی', $buyer->phone],
            [$buyer->id, 'services', 'آسفالت محوطه کارخانه ۳۰۰۰ متر مربع', 3000, 'متر مربع', 'قزوین', 'علی رضایی', $buyer->phone],
            [null, 'emulsion', 'امولسیون قیر برای تک‌کت', 12, 'تن', 'تبریز', 'شرکت عمران آذر', '09141111111'],
            [null, 'aggregates', 'شن و ماسه آسفالتی، ۱۵۰۰ تن ماهانه', 1500, 'تن', 'مشهد', 'آسفالت خراسان', '09152222222'],
        ];
        foreach ($rfqs as [$buyerId, $slug, $title, $qty, $unit, $city, $name, $phone]) {
            Rfq::create([
                'buyer_id' => $buyerId, 'category_id' => $cat($slug), 'title' => $title, 'quantity' => $qty, 'unit' => $unit,
                'city' => $city, 'contact_name' => $name, 'contact_phone' => $phone,
                'details' => 'تحویل ظرف دو هفته. لطفاً قیمت با احتساب حمل تا محل اعلام شود.',
            ]);
        }

        // A quote on the first RFQ, plus a small CRM pipeline for the demo supplier.
        $pars = $companies[0];
        $first = Rfq::first();
        $first->quotes()->create(['company_id' => $pars->id, 'unit_price' => 18_700_000, 'delivery_days' => 7, 'message' => 'شامل حمل تا شیراز، پرداخت ۵۰٪ پیش.']);
        $companies[1]->quotes()->create(['rfq_id' => $first->id, 'unit_price' => 19_100_000, 'delivery_days' => 5]);

        $leads = [
            ['علی رضایی', '09350000001', 'پیمانکاری رضایی', 'rfq', 'negotiation', 3_740_000_000],
            ['مهندس کریمی', '09121234567', 'شهرداری منطقه ۴', 'manual', 'contacted', 1_200_000_000],
            ['شرکت راه‌آفرین', '09131112233', 'راه‌آفرین', 'inquiry', 'new', null],
            ['پیمانکاری سپهر', '09171234000', 'سپهر عمران', 'manual', 'won', 950_000_000],
            ['آقای موسوی', '09190001122', null, 'inquiry', 'lost', 300_000_000],
        ];
        $supplierUser = $pars->owner;
        foreach ($leads as [$name, $phone, $org, $source, $stage, $value]) {
            $contact = $pars->contacts()->create(compact('name', 'phone', 'source', 'stage') + ['organization' => $org, 'deal_value' => $value]);
            $contact->activities()->create(['user_id' => $supplierUser->id, 'type' => 'call', 'body' => 'تماس اولیه و ارسال کاتالوگ محصولات.']);
            if (in_array($stage, ['new', 'contacted', 'negotiation'])) {
                $contact->activities()->create(['user_id' => $supplierUser->id, 'type' => 'task', 'body' => 'پیگیری پیش‌فاکتور', 'due_at' => now()->addDays(random_int(-1, 4))->setTime(10, 0)]);
            }
        }

        $product = $pars->products()->first();
        $pars->inquiries()->create([
            'product_id' => $product->id, 'name' => 'شرکت راه‌آفرین', 'phone' => '09131112233', 'quantity' => 60,
            'crm_contact_id' => CrmContact::where('phone', '09131112233')->value('id'),
            'message' => 'سلام، برای ۶۰ تن قیر ۶۰/۷۰ تحویل یزد قیمت نهایی را اعلام کنید.',
        ]);
    }
}
