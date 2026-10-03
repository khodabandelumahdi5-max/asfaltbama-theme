@php($img = $product->images->firstWhere('kind', 'full') ?? $product->images->first())
<a href="{{ route('parts.show', $product) }}" class="pcard">
    <div class="thumb">
        @if($img)<img src="{{ $img->url() }}" alt="{{ $product->name }}" loading="lazy">@else{{ $product->category->icon ?? '⚙' }}@endif
    </div>
    <div class="body">
        <div>@include('partials.stock')</div>
        <div class="name">{{ $product->name }}</div>
        <div class="small muted">{{ $product->brand->name ?? 'بدون برند' }} · {{ \App\Models\Product::AUTHENTICITY[$product->authenticity] }}</div>
        @if($product->part_number)<div class="small">کد: <span class="code">{{ $product->part_number }}</span></div>@endif
        <div class="price">{{ $product->price ? toman($product->price) : 'قیمت پس از استعلام' }}</div>
        <div class="meta">
            @if($product->stock_status === 'on_request')زمان تأمین: حدود {{ fa_digits($product->lead_time_days) }} روز کاری پس از تأیید@elseif($product->stock_status === 'in_stock')آماده ارسال@else ناموجود — «خبرم کن» @endif
        </div>
    </div>
</a>
