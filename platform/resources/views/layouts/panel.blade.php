<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل کاربری') | {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css?v=1">
    <meta name="robots" content="noindex">
</head>
<body>
@php($me = auth()->user())
<div class="panel">
    <aside class="sidebar">
        <a href="{{ route('home') }}" class="brand" style="padding:0"><img src="/img/logo.svg" alt=""> <span>آسفالت با ما<small>{{ $me->company->name ?? $me->name }}</small></span></a>
        <a href="{{ route('panel.dashboard') }}" @class(['active' => request()->routeIs('panel.dashboard')])>▦ داشبورد</a>

        @if($me->isSupplier())
            <div class="group">فروش</div>
            <a href="{{ route('panel.products.index') }}" @class(['active' => request()->routeIs('panel.products.*')])>📦 محصولات من</a>
            @php($unread = $me->company?->inquiries()->whereNull('read_at')->count())
            <a href="{{ route('panel.inquiries.index') }}" @class(['active' => request()->routeIs('panel.inquiries.*')])>✉ پیام‌های خریداران @if($unread)<span class="count">{{ fa_digits($unread) }}</span>@endif</a>
            <a href="{{ route('rfqs.index') }}">📋 استعلام‌های باز</a>
            <a href="{{ route('panel.quotes.index') }}" @class(['active' => request()->routeIs('panel.quotes.*')])>💬 پیشنهادهای من</a>
            <div class="group">CRM</div>
            <a href="{{ route('panel.crm.index') }}" @class(['active' => request()->routeIs('panel.crm.index', 'panel.crm.show', 'panel.crm.create')])>👥 قیف فروش</a>
            <a href="{{ route('panel.crm.tasks') }}" @class(['active' => request()->routeIs('panel.crm.tasks')])>⏰ پیگیری‌ها</a>
            <div class="group">تنظیمات</div>
            <a href="{{ route('panel.company.edit') }}" @class(['active' => request()->routeIs('panel.company.*')])>🏢 پروفایل شرکت</a>
            @if($me->company)<a href="{{ route('companies.show', $me->company) }}">↗ مشاهده غرفه</a>@endif
        @elseif($me->isAdmin())
            <div class="group">مدیریت</div>
            <a href="{{ route('panel.admin.companies.index') }}" @class(['active' => request()->routeIs('panel.admin.*')])>🏢 تأمین‌کنندگان</a>
        @else
            <div class="group">خرید</div>
            <a href="{{ route('panel.buyer.rfqs') }}" @class(['active' => request()->routeIs('panel.buyer.*')])>📋 استعلام‌های من</a>
            <a href="{{ route('rfqs.create') }}">＋ استعلام جدید</a>
            <a href="{{ route('products.index') }}">🔍 جستجوی محصولات</a>
        @endif

        <div class="group">حساب</div>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="btn btn-ghost btn-sm btn-block" style="background:transparent;color:#cfd3da;border-color:#444b55">خروج</button>
        </form>
    </aside>
    <div class="main">
        @include('partials.flash')
        @yield('content')
    </div>
</div>
</body>
</html>
