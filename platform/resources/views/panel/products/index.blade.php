@extends('layouts.panel')
@section('title', 'محصولات من')

@section('content')
<div class="main-head"><h1>محصولات من</h1><a href="{{ route('panel.products.create') }}" class="btn btn-amber">＋ محصول جدید</a></div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>نام</th><th>دسته</th><th>قیمت</th><th>بازدید</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td><b>{{ $product->name }}</b></td>
                <td class="small">{{ $product->category->name }}</td>
                <td class="small">{{ price_range($product->price_min, $product->price_max) }} / {{ $product->unit }}</td>
                <td>{{ fa_number($product->views) }}</td>
                <td>@if($product->is_active)<span class="badge badge-ok">فعال</span>@else<span class="badge">غیرفعال</span>@endif</td>
                <td class="row" style="flex-wrap:nowrap">
                    <a href="{{ route('products.show', $product) }}" class="btn btn-ghost btn-sm" target="_blank">نمایش</a>
                    <a href="{{ route('panel.products.edit', $product) }}" class="btn btn-ghost btn-sm">ویرایش</a>
                    <form method="POST" action="{{ route('panel.products.destroy', $product) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">حذف</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">هنوز محصولی ثبت نکرده‌اید.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $products->links() }}</div>
@endsection
