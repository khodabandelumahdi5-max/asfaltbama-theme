@extends('layouts.shop')
@section('title', 'درخواست تطبیق و استعلام قطعه')

@section('content')
<div class="container mt" style="max-width:820px">
    <h1>قطعه‌ات را پیدا کنیم</h1>
    <p class="muted">مشخصات خودرو و قطعه را بفرستید. کارشناس فنی سازگاری را بررسی می‌کند و قیمت و زمان تأمین را اعلام می‌کند. {{ config('shop.response_target') }}.</p>
    <form method="POST" action="{{ route('fitment.store') }}" enctype="multipart/form-data" class="card">
        @csrf
        @if($product)
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <div class="alert" style="background:var(--accent-l)">قطعه مورد نظر: <b>{{ $product->name }}</b> @if($product->part_number)<span class="code">{{ $product->part_number }}</span>@endif</div>
        @endif
        <h3>خودرو</h3>
        <div class="field"><label>از فهرست انتخاب کنید</label>@include('partials.vehicle-select', ['name' => 'vehicle_id', 'selected' => old('vehicle_id', $vehicleId)])</div>
        <div class="grid g3">
            <div class="field"><label>یا بنویسید (برند و مدل)</label><input name="vehicle_text" value="{{ old('vehicle_text') }}" placeholder="مثلاً: ام‌وی‌ام X33 کراس"></div>
            <div class="field"><label>سال ساخت (شمسی)</label><input type="number" name="year" value="{{ old('year') }}" placeholder="1399"></div>
            <div class="field"><label>موتور / گیربکس</label><input name="engine" value="{{ old('engine') }}" placeholder="1.5 توربو اتومات"></div>
        </div>
        <div class="field"><label>شماره شاسی (VIN) — اختیاری</label><input name="vin" class="ltr" value="{{ old('vin') }}" maxlength="20">
            <div class="small muted">برای قطعاتی که بین نسخه‌ها فرق دارند لازم است. فقط برای تطبیق استفاده می‌شود و هیچ‌جا نمایش داده نمی‌شود.</div></div>

        <h3 class="mt">قطعه</h3>
        <div class="field"><label>شماره فنی روی قطعه قبلی (اگر دارید)</label><input name="part_number" class="ltr" value="{{ old('part_number') }}"></div>
        <div class="field"><label>چه قطعه‌ای لازم دارید؟ *</label><textarea name="description" rows="3" required placeholder="مثلاً: چراغ جلو سمت راننده، مدل با نوار LED">{{ old('description', request('q')) }}</textarea></div>
        <div class="field"><label>عکس قطعه قبلی، سوکت یا برچسب</label><input type="file" name="photo" accept="image/*">
            <div class="small muted">عکس کمک می‌کند ولی جای بررسی فنی را نمی‌گیرد.</div></div>

        <h3 class="mt">تماس</h3>
        <div class="grid g2">
            <div class="field"><label>نام *</label><input name="name" value="{{ old('name') }}" required></div>
            <div class="field"><label>موبایل *</label><input name="phone" class="ltr" value="{{ old('phone') }}" placeholder="09123456789" required></div>
        </div>
        <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="marketing_consent" value="1" @checked(old('marketing_consent'))> مایلم پیشنهادها و یادآوری‌های مرتبط با خودرویم را دریافت کنم (قابل لغو)</label></div>
        <button class="btn btn-accent">ارسال برای بررسی</button>
    </form>
</div>
@endsection
