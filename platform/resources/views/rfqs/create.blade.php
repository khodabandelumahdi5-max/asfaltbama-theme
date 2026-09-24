@extends('layouts.app')
@section('title', 'ثبت استعلام قیمت')

@section('content')
<div class="container mt" style="max-width:760px">
    <h1>ثبت استعلام قیمت</h1>
    <p class="muted">نیاز خود را دقیق بنویسید تا تأمین‌کنندگان مرتبط، قیمت و زمان تحویل را برایتان ارسال کنند.</p>
    <form method="POST" action="{{ route('rfqs.store') }}" class="card">
        @csrf
        <div class="field"><label>عنوان درخواست *</label><input name="title" value="{{ old('title', request('title')) }}" placeholder="مثلاً: قیر ۶۰/۷۰ بشکه‌ای برای پروژه راهسازی" required></div>
        <div class="grid g3">
            <div class="field"><label>دسته‌بندی</label>
                <select name="category_id"><option value="">—</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label>مقدار *</label><input type="number" step="any" min="0" name="quantity" value="{{ old('quantity') }}" required></div>
            <div class="field"><label>واحد *</label>
                <select name="unit">@foreach(\App\Models\Product::UNITS as $u)<option @selected(old('unit', 'تن') === $u)>{{ $u }}</option>@endforeach</select>
            </div>
        </div>
        <div class="field"><label>شهر محل تحویل</label><input name="city" value="{{ old('city') }}"></div>
        <div class="field"><label>توضیحات (مشخصات فنی، زمان تحویل، نحوه پرداخت)</label><textarea name="details" rows="4">{{ old('details') }}</textarea></div>
        <div class="grid g2">
            <div class="field"><label>نام و نام خانوادگی *</label><input name="contact_name" value="{{ old('contact_name', $user?->name) }}" required></div>
            <div class="field"><label>موبایل *</label><input name="contact_phone" class="ltr" value="{{ old('contact_phone', $user?->phone) }}" placeholder="09123456789" required></div>
        </div>
        @guest<p class="small muted">برای دیدن و مقایسه پیشنهادها در پنل، <a href="{{ route('register') }}" style="color:var(--info)">ثبت‌نام کنید</a>. بدون ثبت‌نام هم تأمین‌کنندگان با شما تماس می‌گیرند.</p>@endguest
        <button class="btn btn-amber">ثبت استعلام</button>
    </form>
</div>
@endsection
