@extends('layouts.shop')

@section('content')
<section class="hero">
    <div class="container">
        <div>
            <h1>قطعه <span>درست</span> برای خودروی شما، با زمان تحویل مشخص</h1>
            <p>{{ config('shop.tagline') }}. پیش از نهایی شدن سفارش، تطبیق قطعه با خودروی شما بررسی می‌شود و قیمت، موجودی و زمان ارسال قبل از پرداخت اعلام می‌شود.</p>
            <div class="row mt">
                <a href="{{ route('parts.index') }}" class="btn btn-accent">جستجوی قطعه</a>
                <a href="{{ route('fitment.create') }}" class="btn btn-ghost">قطعه‌ام را پیدا کن</a>
            </div>
        </div>
        <div class="rfq-box">
            <h3>🚗 خودروی خود را انتخاب کنید</h3>
            <p class="small muted">فقط قطعاتی را می‌بینید که سازگاری‌شان با این خودرو تأیید شده است.</p>
            <form method="POST" action="{{ route('vehicle.set') }}">
                @csrf
                <div class="field">@include('partials.vehicle-select', ['name' => 'vehicle_id', 'selected' => session('vehicle_id'), 'required' => true])</div>
                <button class="btn btn-accent btn-block">نمایش قطعات سازگار</button>
            </form>
            <p class="small muted" style="margin:10px 0 0">خودرویتان در فهرست نیست؟ <a href="{{ route('fitment.create') }}" style="color:var(--info)">درخواست تطبیق بفرستید</a>.</p>
        </div>
    </div>
</section>

<div class="container">
    <div class="section-title"><h2>چه کاری برای شما انجام می‌دهیم</h2><a href="{{ route('pages.quality') }}">روش بررسی کالا ←</a></div>
    <div class="grid g3">
        @foreach(config('shop.promises') as [$icon, $title, $text])
            <div class="card promise"><span class="ic">{{ $icon }}</span><div><b>{{ $title }}</b><small>{{ $text }}</small></div></div>
        @endforeach
    </div>

    <div class="section-title"><h2>دسته‌بندی‌ها</h2><a href="{{ route('parts.index') }}">همه قطعات ←</a></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
        @foreach($categories as $cat)
            <a href="{{ route('parts.index', ['category' => $cat->slug]) }}" class="cat">
                <span class="ic">{{ $cat->icon }}</span><b>{{ $cat->name }}</b>
                <span class="small muted">{{ fa_number($cat->products_count) }} قلم</span>
            </a>
        @endforeach
    </div>

    <div class="section-title"><h2>آماده ارسال {{ session('vehicle_id') ? 'برای خودروی شما' : '' }}</h2><a href="{{ route('parts.index', ['stock' => 'in_stock']) }}">همه کالاهای موجود ←</a></div>
    <div class="grid g4">
        @forelse($ready as $product)
            @include('partials.product-card')
        @empty
            <div class="card empty" style="grid-column:1/-1">فعلاً کالای آماده ارسالی برای این انتخاب نداریم. <a href="{{ route('fitment.create') }}" style="color:var(--info)">درخواست تأمین بفرستید</a>.</div>
        @endforelse
    </div>

    @if($reviews->isNotEmpty())
        <div class="section-title"><h2>نظر خریداران</h2></div>
        <div class="grid g3">
            @foreach($reviews as $review)
                <div class="card">
                    <div class="stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                    <p>{{ $review->body }}</p>
                    <div class="small muted">{{ \Illuminate\Support\Str::of($review->order->customer->name)->explode(' ')->first() }} · خرید تحویل‌شده در {{ jdate($review->order->delivered_at, 'MMMM y') }}</div>
                </div>
            @endforeach
        </div>
        <p class="small muted">فقط نظر خریدارانی که سفارششان تحویل شده و اجازه انتشار داده‌اند نمایش داده می‌شود.</p>
    @endif
</div>
@endsection
