@extends('layouts.app')
@section('title', 'ثبت‌نام')

@section('content')
@php($role = old('role', $role))
<div class="container mt" style="max-width:560px">
    <form method="POST" action="{{ route('register') }}" class="card">
        @csrf
        <h1>ساخت حساب کاربری</h1>
        <div class="grid g2 mb">
            <label class="card" style="padding:12px;cursor:pointer"><input type="radio" name="role" value="buyer" @checked($role !== 'supplier') onchange="toggleCompany()"> <b>خریدار هستم</b><div class="small muted" style="font-weight:400">استعلام قیمت و خرید</div></label>
            <label class="card" style="padding:12px;cursor:pointer"><input type="radio" name="role" value="supplier" @checked($role === 'supplier') onchange="toggleCompany()"> <b>تأمین‌کننده هستم</b><div class="small muted" style="font-weight:400">فروش + CRM رایگان</div></label>
        </div>
        <div id="company-fields" @style(['display:none' => $role !== 'supplier'])>
            <div class="grid g2">
                <div class="field"><label>نام شرکت *</label><input name="company_name" value="{{ old('company_name') }}"></div>
                <div class="field"><label>شهر</label><input name="city" value="{{ old('city') }}"></div>
            </div>
        </div>
        <div class="field"><label>نام و نام خانوادگی</label><input name="name" value="{{ old('name') }}" required></div>
        <div class="grid g2">
            <div class="field"><label>ایمیل</label><input type="email" name="email" class="ltr" value="{{ old('email') }}" required></div>
            <div class="field"><label>موبایل</label><input name="phone" class="ltr" value="{{ old('phone') }}" placeholder="09123456789" required></div>
        </div>
        <div class="grid g2">
            <div class="field"><label>رمز عبور</label><input type="password" name="password" class="ltr" required minlength="8"></div>
            <div class="field"><label>تکرار رمز عبور</label><input type="password" name="password_confirmation" class="ltr" required></div>
        </div>
        <button class="btn btn-amber btn-block">ثبت‌نام</button>
        <p class="small muted" style="text-align:center">قبلاً ثبت‌نام کرده‌اید؟ <a href="{{ route('login') }}" style="color:var(--info)">وارد شوید</a></p>
    </form>
</div>
<script>
function toggleCompany() {
    document.getElementById('company-fields').style.display =
        document.querySelector('input[name=role]:checked').value === 'supplier' ? '' : 'none';
}
</script>
@endsection
