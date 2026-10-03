@extends('layouts.admin')
@section('title', $product->exists ? $product->name : 'قطعه جدید')

@section('content')
@php($manager = auth()->user()->isManager())
@php($selected = old('vehicles', $product->exists ? $product->vehicles->pluck('id')->all() : []))
<div class="main-head">
    <h1>{{ $product->exists ? $product->name : 'قطعه جدید' }}</h1>
    @if($product->exists)<a href="{{ route('parts.show', $product) }}" target="_blank" class="btn btn-ghost btn-sm">نمایش در سایت ↗</a>@endif
</div>

<div class="grid layout-side" style="grid-template-columns:1.4fr 1fr;align-items:start">
<form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card">
    @csrf @if($product->exists) @method('PUT') @endif
    <h3>پرونده قطعه</h3>
    <div class="field"><label>نام دقیق قطعه *</label><input name="name" value="{{ old('name', $product->name) }}" required placeholder="لنت ترمز جلو سرامیکی"></div>
    <div class="grid g3">
        <div class="field"><label>نوع قطعه *</label><select name="part_category_id">@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('part_category_id', $product->part_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label>برند سازنده</label><select name="part_brand_id"><option value="">—</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('part_brand_id', $product->part_brand_id) == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        <div class="field"><label>وضعیت اصالت *</label><select name="authenticity">@foreach(\App\Models\Product::AUTHENTICITY as $k => $l)<option value="{{ $k }}" @selected(old('authenticity', $product->authenticity) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>شماره فنی سازنده</label><input name="part_number" class="ltr" value="{{ old('part_number', $product->part_number) }}"></div>
        <div class="field"><label>شماره OEM خودروساز</label><input name="oem_number" class="ltr" value="{{ old('oem_number', $product->oem_number) }}"></div>
        <div class="field"><label>نو / کارکرده</label><select name="condition">@foreach(\App\Models\Product::CONDITIONS as $k => $l)<option value="{{ $k }}" @selected(old('condition', $product->condition) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>جلو / عقب</label><select name="position"><option value="">—</option>@foreach(\App\Models\Product::POSITIONS as $k => $l)<option value="{{ $k }}" @selected(old('position', $product->position) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>چپ / راست</label><select name="side"><option value="">—</option>@foreach(\App\Models\Product::SIDES as $k => $l)<option value="{{ $k }}" @selected(old('side', $product->side) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>محتویات بسته</label><input name="package_contents" value="{{ old('package_contents', $product->package_contents) }}" placeholder="۴ عدد لنت + سیم سنسور"></div>
    </div>
    <div class="field"><label>توضیحات</label><textarea name="description" rows="3">{{ old('description', $product->description) }}</textarea></div>
    <div class="field"><label>موارد مهم ناسازگاری</label><textarea name="incompatibility_notes" rows="2" placeholder="مثلاً: برای مدل‌های دارای ترمز دستی برقی مناسب نیست">{{ old('incompatibility_notes', $product->incompatibility_notes) }}</textarea></div>
    <div class="grid g2">
        <div class="field"><label>ضمانت</label><input name="warranty" value="{{ old('warranty', $product->warranty) }}" placeholder="۶ ماه ضمانت سلامت تأمین‌کننده"></div>
        <div class="field"><label>شرایط مرجوعی این کالا</label><input name="return_policy" value="{{ old('return_policy', $product->return_policy) }}"></div>
    </div>

    <h3 class="mt">خودروهای سازگار (فقط موارد تأییدشده)</h3>
    <div class="vehicle-list">
        @foreach($makes as $make)
            <b>{{ $make->name }}</b>
            @foreach($make->vehicles as $v)
                @php($v->setRelation('make', $make))
                <label><input type="checkbox" name="vehicles[]" value="{{ $v->id }}" @checked(in_array($v->id, $selected))> {{ $v->label() }}</label>
            @endforeach
        @endforeach
    </div>
    <div class="field mt"><label class="row" style="font-weight:400"><input type="checkbox" name="requires_fitment_check" value="1" @checked(old('requires_fitment_check', $product->requires_fitment_check))> پیش از تأیید هر سفارش، بررسی تطبیق الزامی است (بین نسخه‌ها فرق دارد)</label></div>

    <h3 class="mt">موجودی و قیمت</h3>
    <div class="grid g3">
        <div class="field"><label>وضعیت موجودی *</label><select name="stock_status">@foreach(\App\Models\Product::STOCK as $k => $l)<option value="{{ $k }}" @selected(old('stock_status', $product->stock_status) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>تعداد در دست (برای «موجود»)</label><input type="number" name="stock_qty" min="0" value="{{ old('stock_qty', $product->stock_qty) }}"></div>
        <div class="field"><label>زمان تأمین (روز، برای «قابل تأمین»)</label><input type="number" name="lead_time_days" min="0" value="{{ old('lead_time_days', $product->lead_time_days) }}"></div>
        <div class="field"><label>قیمت فروش (تومان)</label><input type="number" name="price" min="0" value="{{ old('price', $product->price) }}"></div>
        @if($manager)
            <div class="field"><label>حداقل قیمت مجاز (کف)</label><input type="number" name="min_price" min="0" value="{{ old('min_price', $product->min_price) }}"><div class="small muted">اگر خالی باشد، کمترین قیمت خرید کف حساب می‌شود.</div></div>
        @else
            <input type="hidden" name="min_price" value="{{ $product->min_price }}">
        @endif
    </div>
    <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> نمایش در سایت</label></div>
    <button class="btn btn-accent">ذخیره</button>
</form>

<div>
    @if($product->exists)
        <div class="card mb">
            <h3>منابع تأمین</h3>
            <p class="small muted" style="margin-top:-6px">برای اقلام اصلی ترجیحاً دو تأمین‌کننده.</p>
            @forelse($product->offers as $offer)
                <div class="row between" style="padding:6px 0;border-bottom:1px solid var(--line)">
                    <span><b>{{ $offer->supplier->name }}</b>
                        <div class="small muted">@if($manager){{ toman($offer->cost_price) }} · @endif موجودی {{ $offer->stock_qty !== null ? fa_digits($offer->stock_qty) : '؟' }} · {{ $offer->lead_time_days !== null ? fa_digits($offer->lead_time_days).' روز' : '—' }}
                        · اعتبار تا {{ $offer->valid_until ? jdate($offer->valid_until, 'd MMM') : '—' }} @if($offer->isExpired())<span class="overdue">منقضی</span>@endif · ثبت {{ jdate($offer->updated_at, 'd MMM') }}</div></span>
                    <form method="POST" action="{{ route('admin.products.offers.destroy', [$product, $offer->id]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">✕</button></form>
                </div>
            @empty
                <p class="muted small">هنوز منبعی ثبت نشده.</p>
            @endforelse
            <form method="POST" action="{{ route('admin.products.offers.store', $product) }}" class="mt">
                @csrf
                <div class="grid g2">
                    <div class="field"><label>تأمین‌کننده</label><select name="supplier_id" required>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                    <div class="field"><label>قیمت خرید</label><input type="number" name="cost_price" min="0" required></div>
                    <div class="field"><label>موجودی نزد او</label><input type="number" name="stock_qty" min="0"></div>
                    <div class="field"><label>زمان تحویل (روز)</label><input type="number" name="lead_time_days" min="0"></div>
                    <div class="field" style="grid-column:span 2"><label>قیمت و موجودی تا کی برای ما نگه داشته می‌شود؟</label><input type="date" name="valid_until" class="ltr"></div>
                </div>
                <button class="btn btn-sm">ثبت / به‌روزرسانی قیمت</button>
            </form>
        </div>

        <div class="card mb">
            <h3>عکس‌ها</h3>
            <p class="small muted" style="margin-top:-6px">نمای کامل، پشت، سوکت، برچسب کد، بسته‌بندی، و در صورت امکان نصب‌شده.</p>
            <div class="gallery" style="grid-template-columns:repeat(3,1fr)">
                @foreach($product->images as $img)
                    <figure><img src="{{ $img->url() }}" alt=""><figcaption>{{ \App\Models\ProductImage::KINDS[$img->kind] }}@if($img->caption) — {{ $img->caption }}@endif
                        <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $img]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">حذف</button></form></figcaption></figure>
                @endforeach
            </div>
            @php($missing = collect(['full', 'back', 'connector', 'label', 'package'])->diff($product->images->pluck('kind')))
            @if($missing->isNotEmpty())<p class="small" style="color:var(--accent-d)">عکس‌های لازم که هنوز نیست: {{ $missing->map(fn ($k) => \App\Models\ProductImage::KINDS[$k])->implode('، ') }}</p>@endif
            <form method="POST" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="mt">
                @csrf
                <div class="field"><label>نوع عکس</label><select name="kind">@foreach(\App\Models\ProductImage::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label>توضیح (برای «نصب‌شده»: کدام خودرو و نسخه — الزامی)</label><input name="caption"></div>
                <div class="field"><label>فایل عکس</label><input type="file" name="image" accept="image/*"></div>
                <div class="field"><label>یا آدرس عکس</label><input name="url" class="ltr" placeholder="https://"></div>
                <button class="btn btn-sm">افزودن عکس</button>
            </form>
        </div>

        @if($product->alerts->whereNull('notified_at')->isNotEmpty())
            <div class="card"><h3>🔔 منتظر موجود شدن</h3>
                @foreach($product->alerts->whereNull('notified_at') as $a)<div class="small">{{ $a->name }} — <span class="ltr">{{ $a->phone }}</span></div>@endforeach
                <a href="{{ route('admin.alerts.index') }}" class="btn btn-ghost btn-sm mt">مدیریت</a>
            </div>
        @endif
    @else
        <div class="card muted small">پس از ذخیره، منابع تأمین و عکس‌ها را اضافه کنید.</div>
    @endif
</div>
</div>
<style>@media(max-width:1100px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
