<?php

/*
| Store promises. Everything shown to customers about speed, hours and shipping
| comes from here, so each promise has one place to keep it honest.
*/

return [
    'name' => 'قطعه فوری',
    'tagline' => 'تأمین تخصصی قطعات خودروهای چینی، کره‌ای و ژاپنی',
    'phone' => env('SHOP_PHONE', '021-00000000'),
    'whatsapp' => env('SHOP_WHATSAPP'),

    // Who answers, and when.
    'support_hours' => 'شنبه تا چهارشنبه ۹ تا ۱۸ · پنجشنبه ۹ تا ۱۳',
    'response_target' => 'پاسخ استعلام و درخواست تطبیق در ساعات کاری حداکثر تا ۲ ساعت',

    // Definition of «فوری»: applies only to in-stock items, ordered and paid before the cutoff.
    'same_day_city' => 'تهران',
    'same_day_cutoff' => '13:00',

    /*
    | Promises shown on the home page. Keep only those you can deliver on every
    | order, and make sure each one has an owner (see ROLES in App\Models\User).
    */
    'promises' => [
        ['🔍', 'بررسی تطبیق پیش از سفارش', 'با مدل، سال، موتور و در صورت نیاز شماره فنی یا شاسی. نتیجه بررسی کنار سفارش ثبت می‌شود.'],
        ['⏱', 'زمان تأمین قبل از پرداخت', 'برای کالای «قابل تأمین»، قیمت و زمان را پس از تأیید با تأمین‌کننده اعلام می‌کنیم؛ پرداخت بعد از تأیید شما.'],
        ['📦', 'عکس کالای آماده ارسال', 'برای قطعات حساس (برقی، چراغ، سنسور) پیش از ارسال، عکس همان کالایی را که ارسال می‌شود می‌فرستیم.'],
        ['🏷', 'اصالت جداگانه برای هر قطعه', 'برای هر کالا برند سازنده و وضعیت اصالت (جنیون، OEM یا یدکی برند) جداگانه نوشته شده است.'],
        ['🚚', 'تعریف دقیق «فوری»', 'کالای «موجود» در تهران، اگر تا ساعت ۱۳ پرداخت شود، همان روز ارسال می‌شود. بقیه موارد با زمان اعلام‌شده.'],
        ['↩', 'رسیدگی مشخص به ارسال اشتباه', 'روند، مهلت و هزینه تعویض در صفحه شرایط ارسال و مرجوعی نوشته شده است.'],
    ],

    // Shipping charged to the customer, shown before payment. 'eta' in working days after dispatch.
    'shipping' => [
        'تهران' => ['charge' => 120_000, 'eta' => 'همان روز (کالای موجود، سفارش قبل از ساعت ۱۳)'],
        'کرج' => ['charge' => 160_000, 'eta' => '۱ روز کاری'],
        '*' => ['charge' => 220_000, 'eta' => '۲ تا ۴ روز کاری (پست پیشتاز / تیپاکس)'],
    ],

    // Default variable-cost estimates applied to new orders (editable per order).
    'defaults' => [
        'packaging_cost' => 25_000,
        'payment_fee_percent' => 1.0,
        'return_reserve_percent' => 2.0,
    ],

    'cities' => ['تهران', 'کرج', 'اصفهان', 'مشهد', 'شیراز', 'تبریز', 'قم', 'اهواز', 'رشت', 'کرمانشاه', 'سایر'],
];
