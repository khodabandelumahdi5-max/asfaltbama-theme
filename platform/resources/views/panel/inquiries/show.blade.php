@extends('layouts.panel')
@section('title', 'پیام '.$inquiry->name)

@section('content')
<div class="main-head">
    <h1>پیام از {{ $inquiry->name }}</h1>
    <a href="{{ route('panel.inquiries.index') }}" class="btn btn-ghost btn-sm">بازگشت</a>
</div>
<div class="grid" style="grid-template-columns:1fr 300px;align-items:start">
    <div class="card">
        <div class="small muted">درباره: @if($inquiry->product)<a href="{{ route('products.show', $inquiry->product) }}" style="color:var(--info)">{{ $inquiry->product->name }}</a>@else — @endif · {{ jdate($inquiry->created_at, 'd MMMM y، HH:mm') }}</div>
        @if($inquiry->quantity)<p><b>مقدار درخواستی:</b> {{ fa_number($inquiry->quantity, 2) }} {{ $inquiry->product->unit ?? '' }}</p>@endif
        <p style="white-space:pre-line">{{ $inquiry->message }}</p>
    </div>
    <div class="card">
        <h3>اقدام</h3>
        <a href="tel:{{ $inquiry->phone }}" class="btn btn-amber btn-block mb">📞 تماس با <span class="ltr">{{ $inquiry->phone }}</span></a>
        @if($inquiry->contact)
            <a href="{{ route('panel.crm.show', $inquiry->contact) }}" class="btn btn-ghost btn-block">پرونده در CRM</a>
            <p class="small muted" style="text-align:center">وضعیت: @include('partials.stage-badge', ['stage' => $inquiry->contact->stage])</p>
        @endif
    </div>
</div>
@endsection
