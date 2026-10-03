<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل') | {{ config('shop.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="/fonts/Vazirmatn-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/app.css?v=2">
    <meta name="robots" content="noindex">
</head>
<body>
@php($me = auth()->user())
@php($c = ['orders' => \App\Models\Order::where('status', 'awaiting_confirmation')->count(), 'fit' => \App\Models\FitmentRequest::where('status', 'pending')->count(), 'alerts' => \App\Models\StockAlert::whereNull('notified_at')->whereHas('product', fn ($q) => $q->where('stock_status', 'in_stock'))->count()])
<div class="panel">
    <aside class="sidebar">
        <a href="{{ route('home') }}" class="brand" style="padding:0" target="_blank"><img src="/img/logo.svg" alt=""> <span>{{ config('shop.name') }}<small>{{ $me->name }} · {{ \App\Models\User::ROLES[$me->role] ?? $me->role }}</small></span></a>
        <a href="{{ route('admin.dashboard') }}" @class(['active' => request()->routeIs('admin.dashboard')])>▦ کارهای امروز</a>
        <div class="group">فروش</div>
        <a href="{{ route('admin.orders.index') }}" @class(['active' => request()->routeIs('admin.orders.*')])>🧾 سفارش‌ها @if($c['orders'])<span class="count">{{ fa_digits($c['orders']) }}</span>@endif</a>
        <a href="{{ route('admin.fitment.index') }}" @class(['active' => request()->routeIs('admin.fitment.*')])>🔍 تطبیق و استعلام @if($c['fit'])<span class="count">{{ fa_digits($c['fit']) }}</span>@endif</a>
        <a href="{{ route('admin.alerts.index') }}" @class(['active' => request()->routeIs('admin.alerts.*')])>🔔 خبرم کن @if($c['alerts'])<span class="count">{{ fa_digits($c['alerts']) }}</span>@endif</a>
        <a href="{{ route('admin.customers.index') }}" @class(['active' => request()->routeIs('admin.customers.*')])>👥 مشتریان</a>
        <div class="group">کالا و تأمین</div>
        <a href="{{ route('admin.products.index') }}" @class(['active' => request()->routeIs('admin.products.*')])>⚙ قطعات</a>
        <a href="{{ route('admin.suppliers.index') }}" @class(['active' => request()->routeIs('admin.suppliers.*')])>🏪 تأمین‌کنندگان</a>
        <a href="{{ route('admin.reviews.index') }}" @class(['active' => request()->routeIs('admin.reviews.*')])>★ نظرات خریداران</a>
        @if($me->isManager())
            <div class="group">مدیریت</div>
            <a href="{{ route('admin.report') }}" @class(['active' => request()->routeIs('admin.report')])>📊 گزارش هفتگی</a>
        @endif
        <div class="group">حساب</div>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf
            <button class="btn btn-ghost btn-sm btn-block" style="background:transparent;color:#cfd3da;border-color:#3c5a7a">خروج</button>
        </form>
    </aside>
    <div class="main">
        @include('partials.flash')
        @yield('content')
    </div>
</div>
</body>
</html>
