@extends('layouts.shop')
@section('title', $product->name.($product->part_number ? ' '.$product->part_number : ''))
@section('description', \Illuminate\Support\Str::limit($product->name.' — '.($product->brand->name ?? '').' — '.\App\Models\Product::STOCK[$product->stock_status], 150))

@push('head')
{{-- Structured data mirrors exactly what the page shows (price + availability). --}}
<script type="application/ld+json">{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->name,
    'sku' => $product->part_number,
    'mpn' => $product->part_number,
    'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
    'image' => $product->images->where('kind', '!=', 'illustration')->map->url()->values()->all() ?: null,
    'description' => $product->description,
    'itemCondition' => $product->condition === 'new' ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition',
    'offers' => $product->price ? [
        '@type' => 'Offer',
        'priceCurrency' => 'IRR',
        'price' => $product->price * 10,
        'availability' => $product->schemaAvailability(),
        'url' => route('parts.show', $product),
    ] : null,
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
@php($main = $product->images->firstWhere('kind', 'full') ?? $product->images->first())
<div class="container mt">
    <div class="small muted mb">
        <a href="{{ route('home') }}">خانه</a> › <a href="{{ route('parts.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a> › {{ $product->name }}
    </div>
    <div class="grid layout-3" style="grid-template-columns:1fr 1.2fr 320px;align-items:start">
        <div>
            <div class="pcard"><div class="thumb" style="aspect-ratio:1">
                @if($main)<img src="{{ $main->url() }}" alt="{{ $product->name }}">@else<span style="font-size:5rem">{{ $product->category->icon }}</span>@endif
            </div></div>
            @if($product->images->count() > 1)
                <div class="gallery">
                    @foreach($product->images as $img)
                        <figure><a href="{{ $img->url() }}" target="_blank"><img src="{{ $img->url() }}" alt="{{ \App\Models\ProductImage::KINDS[$img->kind] }}" loading="lazy"></a>
                            <figcaption>{{ \App\Models\ProductImage::KINDS[$img->kind] }}@if($img->caption) — {{ $img->caption }}@endif</figcaption></figure>
                    @endforeach
                </div>
            @endif
            @unless($product->images->count())<p class="small muted">عکس واقعی این کالا هنوز ثبت نشده است. برای دریافت عکس کالای موجود تماس بگیرید.</p>@endunless
        </div>

        <div>
            <h1 style="margin-bottom:6px">{{ $product->name }}</h1>
            <div class="row mb">@include('partials.stock') <span class="badge">{{ \App\Models\Product::AUTHENTICITY[$product->authenticity] }}</span></div>

            @if($fitsMyVehicle === true)
                <div class="fit fit-yes mb">✔ سازگاری با «{{ \App\Http\Controllers\ShopController::currentVehicle()->label() }}» تأیید شده است.</div>
            @elseif($fitsMyVehicle === false)
                <div class="fit fit-no mb">✖ سازگاری این قطعه با خودروی انتخابی شما تأیید نشده است. <a href="{{ route('fitment.create', ['product' => $product->slug]) }}" style="text-decoration:underline">درخواست بررسی</a></div>
            @else
                <div class="fit fit-unknown mb">خودروی خود را از نوار بالا انتخاب کنید تا سازگاری را ببینید.</div>
            @endif

            <table class="spec">
                <tr><th>برند سازنده</th><td>{{ $product->brand->name ?? '—' }} @if($product->brand?->country)<span class="muted small">({{ $product->brand->country }})</span>@endif</td></tr>
                <tr><th>شماره فنی</th><td>@if($product->part_number)<span class="code">{{ $product->part_number }}</span>@else — @endif</td></tr>
                @if($product->oem_number)<tr><th>شماره OEM خودروساز</th><td><span class="code">{{ $product->oem_number }}</span></td></tr>@endif
                <tr><th>وضعیت</th><td>{{ \App\Models\Product::CONDITIONS[$product->condition] }}</td></tr>
                @if($product->side || $product->position)<tr><th>محل نصب</th><td>{{ collect([\App\Models\Product::POSITIONS[$product->position] ?? null, \App\Models\Product::SIDES[$product->side] ?? null])->filter()->implode(' — ') }}</td></tr>@endif
                @if($product->package_contents)<tr><th>محتویات بسته</th><td>{{ $product->package_contents }}</td></tr>@endif
                <tr><th>زمان ارسال</th><td>
                    @if($product->stock_status === 'in_stock') آماده ارسال؛ {{ config('shop.same_day_city') }}: همان روز در صورت پرداخت تا ساعت {{ fa_digits(config('shop.same_day_cutoff')) }}
                    @elseif($product->stock_status === 'on_request') حدود {{ fa_digits($product->lead_time_days) }} روز کاری پس از تأیید سفارش — زمان دقیق پیش از پرداخت اعلام می‌شود
                    @else فعلاً ناموجود @endif
                </td></tr>
                <tr><th>ضمانت</th><td>{{ $product->warranty ?: 'بدون ضمانت سازنده' }}</td></tr>
                <tr><th>مرجوعی</th><td>{{ $product->return_policy ?: 'طبق شرایط عمومی' }} — <a href="{{ route('pages.shipping') }}" style="color:var(--info)">شرایط</a></td></tr>
            </table>

            <h3 class="mt">خودروهای سازگار (تأییدشده)</h3>
            @if($product->vehicles->isNotEmpty())
                <ul style="margin-top:0">
                    @foreach($product->vehicles as $v)<li>{{ $v->label() }} @if($v->pivot->note)<span class="small muted">— {{ $v->pivot->note }}</span>@endif</li>@endforeach
                </ul>
            @else
                <p class="muted">سازگاری این قطعه هنوز برای خودروی خاصی ثبت نشده — پیش از خرید درخواست تطبیق بفرستید.</p>
            @endif

            @if($product->incompatibility_notes)
                <div class="warn mt"><b>⚠ موارد ناسازگاری:</b> {{ $product->incompatibility_notes }}</div>
            @endif

            @if($product->description)
                <h3 class="mt">توضیحات</h3>
                <div style="white-space:pre-line">{{ $product->description }}</div>
            @endif
            @if($product->brand?->notes)
                <h3 class="mt">درباره برند {{ $product->brand->name }}</h3>
                <p class="small">{{ $product->brand->notes }}</p>
            @endif
        </div>

        <aside>
            <div class="card mb">
                <div class="price" style="font-size:1.5rem;color:var(--ink);font-weight:800">{{ $product->price ? toman($product->price) : 'قیمت پس از استعلام' }}</div>
                @if($product->price_checked_at)<div class="small muted">قیمت بررسی‌شده در {{ jdate($product->price_checked_at, 'd MMMM') }}</div>@endif

                @if($product->isOrderable())
                    @if($product->stock_status === 'on_request')
                        <p class="small" style="color:var(--info)">این کالا «قابل تأمین» است، نه موجود در انبار. پس از ثبت، قیمت و زمان دقیق را با تأمین‌کننده تأیید و اعلام می‌کنیم؛ پرداخت پس از تأیید شما.</p>
                    @endif
                    @if($product->requires_fitment_check || $fitsMyVehicle !== true)
                        <p class="small muted">پیش از ارسال، تطبیق این قطعه با خودروی شما بررسی می‌شود.</p>
                    @endif
                    <form method="POST" action="{{ route('cart.add', $product) }}" class="row">
                        @csrf
                        <input type="number" name="qty" value="1" min="1" max="99" style="width:80px">
                        <button class="btn btn-accent" style="flex:1">افزودن به سبد</button>
                    </form>
                @elseif($product->stock_status === 'out_of_stock')
                    <form method="POST" action="{{ route('parts.alert', $product) }}">
                        @csrf
                        <h3 style="margin-top:12px">خبرم کن موجود شد</h3>
                        <div class="field"><input name="name" placeholder="نام" value="{{ old('name') }}" required></div>
                        <div class="field"><input name="phone" class="ltr" placeholder="09123456789" value="{{ old('phone') }}" required></div>
                        <p class="small muted">فقط برای اطلاع از موجود شدن همین کالا تماس می‌گیریم.</p>
                        <button class="btn btn-block">ثبت</button>
                    </form>
                @endif
            </div>
            <a href="{{ route('fitment.create', ['product' => $product->slug]) }}" class="btn btn-ghost btn-block mb">🔍 مطمئن نیستید می‌خورد؟ درخواست تطبیق</a>
            <div class="card small">
                <b>پاسخ‌گویی:</b> {{ config('shop.support_hours') }}<br>
                <span class="muted">{{ config('shop.response_target') }}</span>
            </div>
        </aside>
    </div>

    @if($alternatives->isNotEmpty())
        <div class="section-title"><h2>برندهای دیگر برای همین خودروها</h2></div>
        <div class="grid g4">
            @foreach($alternatives as $alt)
                @include('partials.product-card', ['product' => $alt])
            @endforeach
        </div>
    @endif
</div>
<style>@media(max-width:980px){.layout-3{grid-template-columns:1fr!important}}</style>
@endsection
