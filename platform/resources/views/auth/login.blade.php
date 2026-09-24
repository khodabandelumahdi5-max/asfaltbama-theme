@extends('layouts.app')
@section('title', 'ورود')

@section('content')
<div class="container mt" style="max-width:440px">
    <form method="POST" action="{{ route('login') }}" class="card">
        @csrf
        <h1>ورود به حساب</h1>
        <div class="field"><label>ایمیل</label><input type="email" name="email" class="ltr" value="{{ old('email') }}" required autofocus></div>
        <div class="field"><label>رمز عبور</label><input type="password" name="password" class="ltr" required></div>
        <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label></div>
        <button class="btn btn-amber btn-block">ورود</button>
        <p class="small muted" style="text-align:center">حساب ندارید؟ <a href="{{ route('register') }}" style="color:var(--info)">ثبت‌نام کنید</a></p>
    </form>
</div>
@endsection
