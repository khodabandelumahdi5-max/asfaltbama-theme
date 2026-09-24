@extends('layouts.panel')
@section('title', $product->exists ? 'ویرایش محصول' : 'محصول جدید')

@section('content')
<div class="main-head"><h1>{{ $product->exists ? 'ویرایش محصول' : 'محصول جدید' }}</h1></div>
<form method="POST" action="{{ $product->exists ? route('panel.products.update', $product) : route('panel.products.store') }}" class="card" style="max-width:820px">
    @csrf @if($product->exists) @method('PUT') @endif
    <div class="field"><label>نام محصول *</label><input name="name" value="{{ old('name', $product->name) }}" placeholder="قیر ۶۰/۷۰ پالایشگاهی" required></div>
    <div class="grid g3">
        <div class="field"><label>دسته‌بندی *</label>
            <select name="category_id" required>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label>واحد فروش *</label>
            <select name="unit">@foreach(\App\Models\Product::UNITS as $u)<option @selected(old('unit', $product->unit) === $u)>{{ $u }}</option>@endforeach</select>
        </div>
        <div class="field"><label>حداقل سفارش *</label><input type="number" step="any" min="0" name="min_order" value="{{ old('min_order', $product->min_order) }}" required></div>
    </div>
    <div class="grid g2">
        <div class="field"><label>حداقل قیمت (تومان)</label><input type="number" min="0" name="price_min" value="{{ old('price_min', $product->price_min) }}"></div>
        <div class="field"><label>حداکثر قیمت (تومان)</label><input type="number" min="0" name="price_max" value="{{ old('price_max', $product->price_max) }}"></div>
    </div>
    <p class="small muted" style="margin-top:-8px">اگر قیمت را خالی بگذارید، «قیمت توافقی» نمایش داده می‌شود.</p>
    <div class="field"><label>آدرس تصویر</label><input name="image_url" class="ltr" value="{{ old('image_url', $product->image_url) }}" placeholder="https://"></div>
    <div class="field"><label>توضیحات و مشخصات فنی</label><textarea name="description" rows="6">{{ old('description', $product->description) }}</textarea></div>
    <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> نمایش در بازار</label></div>
    <div class="row"><button class="btn btn-amber">ذخیره</button><a href="{{ route('panel.products.index') }}" class="btn btn-ghost">انصراف</a></div>
</form>
@endsection
