@extends('layouts.app')
@section('title', $product->name)
@section('description', \Illuminate\Support\Str::limit(strip_tags($product->description), 150))

@section('content')
<div class="container mt">
    <div class="small muted mb">
        <a href="{{ route('home') }}">خانه</a> ›
        <a href="{{ route('products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a> ›
        {{ $product->name }}
    </div>
    <div class="grid" style="grid-template-columns:1.1fr 1fr 320px;align-items:start">
        <div class="pcard"><div class="thumb" style="aspect-ratio:1">
            @if($product->image_url)<img src="{{ $product->image_url }}" alt="{{ $product->name }}">@else<span style="font-size:5rem">{{ $product->category->icon }}</span>@endif
        </div></div>
        <div>
            <h1>{{ $product->name }}</h1>
            <div class="card" style="background:var(--amber-l);border-color:#fde68a">
                <div class="price" style="font-size:1.4rem;color:var(--bad);font-weight:800">{{ price_range($product->price_min, $product->price_max) }}</div>
                <div class="small muted">به ازای هر {{ $product->unit }} · حداقل سفارش {{ fa_number($product->min_order, 2) }} {{ $product->unit }}</div>
            </div>
            <table class="mt" style="border:1px solid var(--line);border-radius:10px">
                <tr><th>دسته‌بندی</th><td>{{ $product->category->name }}</td></tr>
                <tr><th>واحد</th><td>{{ $product->unit }}</td></tr>
                <tr><th>محل تأمین</th><td>{{ $product->company->city ?? '—' }}</td></tr>
                <tr><th>بازدید</th><td>{{ fa_number($product->views) }}</td></tr>
            </table>
            <h3 class="mt">توضیحات</h3>
            <div style="white-space:pre-line">{{ $product->description ?: 'توضیحی ثبت نشده است.' }}</div>
        </div>
        <aside>
            <div class="card mb">
                <div class="small muted">تأمین‌کننده</div>
                <a href="{{ route('companies.show', $product->company) }}"><h3 style="margin:0">{{ $product->company->name }}</h3></a>
                @if($product->company->is_verified)<span class="badge badge-ok">✔ تأییدشده</span>@else<span class="badge">در انتظار تأیید</span>@endif
                <div class="small muted mt" style="margin-top:8px">{{ $product->company->city }}</div>
            </div>
            <form class="card" method="POST" action="{{ route('inquiries.store', $product) }}">
                @csrf
                <h3>تماس با تأمین‌کننده</h3>
                <div class="field"><label>نام شما</label><input name="name" value="{{ old('name', auth()->user()?->name) }}" required></div>
                <div class="field"><label>موبایل</label><input name="phone" class="ltr" value="{{ old('phone', auth()->user()?->phone) }}" placeholder="09123456789" required></div>
                <div class="field"><label>مقدار مورد نیاز ({{ $product->unit }})</label><input name="quantity" type="number" step="any" min="0" value="{{ old('quantity') }}"></div>
                <div class="field"><label>پیام</label><textarea name="message" rows="3" required>{{ old('message', 'سلام، لطفاً قیمت نهایی و شرایط تحویل را اعلام کنید.') }}</textarea></div>
                <button class="btn btn-amber btn-block">ارسال پیام</button>
            </form>
        </aside>
    </div>

    @if($related->isNotEmpty())
        <div class="section-title"><h2>محصولات مشابه</h2></div>
        <div class="grid g4">
            @foreach($related as $product)
                @include('partials.product-card')
            @endforeach
        </div>
    @endif
</div>
<style>@media(max-width:960px){.container>.grid[style]{grid-template-columns:1fr!important}}</style>
@endsection
