<a href="{{ route('products.show', $product) }}" class="pcard">
    <div class="thumb">
        @if($product->image_url)
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
        @else
            {{ $product->category->icon ?? '🛢' }}
        @endif
    </div>
    <div class="body">
        <div class="name">{{ $product->name }}</div>
        <div class="price">{{ price_range($product->price_min, $product->price_max) }} <span class="muted small">/ {{ $product->unit }}</span></div>
        <div class="small muted">حداقل سفارش: {{ fa_number($product->min_order, 2) }} {{ $product->unit }}</div>
        <div class="meta">
            {{ $product->company->name }}
            @if($product->company->is_verified)<span class="verified" title="تأمین‌کننده تأییدشده">✔</span>@endif
            @if($product->company->city) · {{ $product->company->city }}@endif
        </div>
    </div>
</a>
