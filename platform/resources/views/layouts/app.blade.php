<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'بازار آنلاین قیر و آسفالت') | {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'آسفالت با ما؛ بازار B2B قیر، آسفالت، امولسیون و خدمات راهسازی. استعلام قیمت از تأمین‌کنندگان تأییدشده.')">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css?v=1">
</head>
<body>
<div class="topbar">
    <div class="container">
        <span>☎ پشتیبانی: ۰۲۱-۰۰۰۰۰۰۰۰ · شنبه تا پنجشنبه ۸ تا ۱۷</span>
        <span class="row" style="gap:16px">
            <a href="{{ route('register', ['role' => 'supplier']) }}">فروشنده شوید</a>
            <a href="{{ route('rfqs.index') }}">بازار استعلام‌ها</a>
        </span>
    </div>
</div>
<header class="header">
    <div class="container">
        <a href="{{ route('home') }}" class="brand">
            <img src="/img/logo.svg" alt="">
            <span>آسفالت با ما<small>بازار B2B قیر و آسفالت</small></span>
        </a>
        <form action="{{ route('products.index') }}" class="search" role="search">
            <input name="q" value="{{ request('q') }}" placeholder="جستجوی محصول: قیر ۶۰/۷۰، آسفالت گرم، امولسیون…" aria-label="جستجو">
            <button>جستجو</button>
        </form>
        <div class="nav-actions">
            @auth
                <a href="{{ route('panel.dashboard') }}" class="btn btn-ghost btn-sm">پنل من</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">ورود</a>
                <a href="{{ route('register') }}" class="btn btn-sm">ثبت‌نام</a>
            @endauth
            <a href="{{ route('rfqs.create') }}" class="btn btn-amber btn-sm">استعلام قیمت</a>
        </div>
    </div>
</header>
<nav class="catbar">
    <div class="container">
        <a href="{{ route('products.index') }}"><b>☰ همه دسته‌ها</b></a>
        @foreach(\App\Models\Category::whereNull('parent_id')->orderBy('sort')->get() as $navCat)
            <a href="{{ route('products.index', ['category' => $navCat->slug]) }}">{{ $navCat->name }}</a>
        @endforeach
        <a href="{{ route('companies.index') }}">تأمین‌کنندگان</a>
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
            <div class="brand" style="color:#fff"><img src="/img/logo.svg" alt="" width="36"> آسفالت با ما</div>
            <p class="small">پل ارتباطی خریداران و تأمین‌کنندگان قیر، آسفالت و خدمات راهسازی در سراسر ایران.</p>
        </div>
        <div>
            <h4>خریداران</h4>
            <ul>
                <li><a href="{{ route('rfqs.create') }}">ثبت استعلام قیمت</a></li>
                <li><a href="{{ route('products.index') }}">جستجوی محصولات</a></li>
                <li><a href="{{ route('companies.index') }}">تأمین‌کنندگان تأییدشده</a></li>
            </ul>
        </div>
        <div>
            <h4>تأمین‌کنندگان</h4>
            <ul>
                <li><a href="{{ route('register', ['role' => 'supplier']) }}">ثبت‌نام فروشنده</a></li>
                <li><a href="{{ route('rfqs.index') }}">استعلام‌های باز</a></li>
                <li><a href="{{ route('login') }}">ورود به CRM</a></li>
            </ul>
        </div>
        <div>
            <h4>ارتباط با ما</h4>
            <ul class="small">
                <li>تهران</li>
                <li>info@asfaltbama.ir</li>
            </ul>
        </div>
    </div>
    <div class="container copy small">© {{ fa_digits(jdate(now(), 'y')) }} آسفالت با ما — همه حقوق محفوظ است.</div>
</footer>
</body>
</html>
