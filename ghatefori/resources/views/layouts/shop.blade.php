<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'قطعات خودروهای چینی، کره‌ای و ژاپنی') | {{ config('shop.name') }}</title>
    <meta name="description" content="@yield('description', config('shop.tagline').'. بررسی تطبیق قطعه با خودرو پیش از نهایی شدن سفارش.')">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css?v=1">
    @stack('head')
</head>
<body>
@php($myVehicle = \App\Http\Controllers\ShopController::currentVehicle())
<div class="topbar">
    <div class="container">
        <span>☎ <span class="ltr">{{ config('shop.phone') }}</span> · پاسخ‌گویی: {{ config('shop.support_hours') }}</span>
        <span class="row" style="gap:16px">
            <a href="{{ route('orders.track') }}">پیگیری سفارش</a>
            <a href="{{ route('pages.shipping') }}">ارسال و مرجوعی</a>
        </span>
    </div>
</div>
<header class="header">
    <div class="container">
        <a href="{{ route('home') }}" class="brand">
            <img src="/img/logo.svg" alt="">
            <span>{{ config('shop.name') }}<small>{{ config('shop.tagline') }}</small></span>
        </a>
        <form action="{{ route('parts.index') }}" class="search" role="search">
            <input name="q" value="{{ request('q') }}" placeholder="نام قطعه یا شماره فنی روی قطعه (مثلاً فیلتر روغن)" aria-label="جستجو">
            <button>جستجو</button>
        </form>
        <div class="nav-actions">
            <a href="{{ route('fitment.create') }}" class="btn btn-ghost btn-sm">درخواست تطبیق</a>
            <a href="{{ route('cart.index') }}" class="btn btn-sm">🛒 سبد @if(\App\Support\Cart::count())({{ fa_digits(\App\Support\Cart::count()) }})@endif</a>
        </div>
    </div>
</header>
<div class="vbar">
    <div class="container">
        @if($myVehicle)
            <span>🚗 خودروی شما: <b>{{ $myVehicle->label() }}</b> — فقط قطعات تأییدشده برای این خودرو نمایش داده می‌شوند.</span>
            <form method="POST" action="{{ route('vehicle.clear') }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">تغییر خودرو</button></form>
        @else
            <form method="POST" action="{{ route('vehicle.set') }}" class="row" style="gap:8px">
                @csrf
                <span>🚗 اول خودروی خود را انتخاب کنید:</span>
                @include('partials.vehicle-select', ['name' => 'vehicle_id', 'selected' => null, 'required' => true, 'submitOnChange' => true])
                <noscript><button class="btn btn-sm">انتخاب</button></noscript>
            </form>
        @endif
    </div>
</div>
<nav class="catbar">
    <div class="container">
        @foreach(\App\Models\PartCategory::orderBy('sort')->get() as $navCat)
            <a href="{{ route('parts.index', ['category' => $navCat->slug]) }}">{{ $navCat->icon }} {{ $navCat->name }}</a>
        @endforeach
    </div>
</nav>

<main>
    @if(session('status') || $errors->any())
        <div class="container mt">@include('partials.flash')</div>
    @endif
    @yield('content')
</main>

<footer class="footer">
    <div class="container grid g4">
        <div>
            <div class="brand" style="color:#fff"><img src="/img/logo.svg" alt="" width="36"> {{ config('shop.name') }}</div>
            <p class="small">{{ config('shop.tagline') }}. خرید از بنکداران معتبر بازار، با بررسی تطبیق و کنترل کالا پیش از ارسال.</p>
        </div>
        <div>
            <h4>خرید</h4>
            <ul>
                <li><a href="{{ route('parts.index') }}">جستجوی قطعه</a></li>
                <li><a href="{{ route('fitment.create') }}">درخواست تطبیق و استعلام</a></li>
                <li><a href="{{ route('orders.track') }}">پیگیری سفارش</a></li>
            </ul>
        </div>
        <div>
            <h4>اطمینان</h4>
            <ul>
                <li><a href="{{ route('pages.quality') }}">روش بررسی کالا</a></li>
                <li><a href="{{ route('pages.shipping') }}">شرایط ارسال، «فوری» و مرجوعی</a></li>
            </ul>
        </div>
        <div>
            <h4>تماس</h4>
            <ul class="small">
                <li class="ltr">{{ config('shop.phone') }}</li>
                <li>{{ config('shop.support_hours') }}</li>
                <li><a href="{{ route('pages.contact') }}">صفحه تماس</a></li>
            </ul>
        </div>
    </div>
    <div class="container copy small">© {{ jdate(now(), 'y') }} {{ config('shop.name') }}</div>
</footer>
</body>
</html>
