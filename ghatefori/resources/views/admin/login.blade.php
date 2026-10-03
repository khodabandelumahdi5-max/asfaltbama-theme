<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود کارکنان | {{ config('shop.name') }}</title>
    <link rel="preload" href="/fonts/Vazirmatn-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/app.css?v=2"><meta name="robots" content="noindex">
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:16px">
<form method="POST" action="{{ route('admin.login') }}" class="card" style="width:100%;max-width:400px">
    @csrf
    <div class="brand mb"><img src="/img/logo.svg" alt=""> <span>{{ config('shop.name') }}<small>ورود کارکنان</small></span></div>
    @include('partials.flash')
    <div class="field"><label>ایمیل</label><input type="email" name="email" class="ltr" value="{{ old('email') }}" required autofocus></div>
    <div class="field"><label>رمز عبور</label><input type="password" name="password" class="ltr" required></div>
    <button class="btn btn-accent btn-block">ورود</button>
</form>
</body>
</html>
