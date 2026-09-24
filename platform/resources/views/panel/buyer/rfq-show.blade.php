@extends('layouts.panel')
@section('title', $rfq->title)

@section('content')
<div class="main-head">
    <h1>{{ $rfq->title }}</h1>
    @if($rfq->isOpen())<span class="badge badge-ok">باز — در انتظار انتخاب</span>@else<span class="badge">بسته شده</span>@endif
</div>
<p class="muted">{{ fa_number($rfq->quantity, 2) }} {{ $rfq->unit }} · {{ $rfq->city ?? '—' }} · {{ jdate($rfq->created_at) }}</p>

<h2>پیشنهادهای دریافتی ({{ fa_number($quotes->count()) }})</h2>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>تأمین‌کننده</th><th>قیمت واحد</th><th>مبلغ کل</th><th>تحویل</th><th>توضیحات</th><th></th></tr></thead>
        <tbody>
        @forelse($quotes as $quote)
            <tr>
                <td><a href="{{ route('companies.show', $quote->company) }}" target="_blank"><b>{{ $quote->company->name }}</b></a> @if($quote->company->is_verified)<span class="verified">✔</span>@endif</td>
                <td>{{ toman($quote->unit_price) }}</td>
                <td>{{ toman((int) round($quote->unit_price * $rfq->quantity)) }}</td>
                <td>{{ $quote->delivery_days !== null ? fa_digits($quote->delivery_days).' روز' : '—' }}</td>
                <td class="small">{{ $quote->message }}</td>
                <td>
                    @if($rfq->isOpen())
                        <form method="POST" action="{{ route('panel.buyer.quotes.accept', $quote) }}" onsubmit="return confirm('این پیشنهاد پذیرفته و استعلام بسته شود؟')">@csrf
                            <button class="btn btn-amber btn-sm">پذیرش</button>
                        </form>
                    @else
                        <span class="badge {{ $quote->status === 'accepted' ? 'badge-ok' : '' }}">{{ \App\Models\Quote::STATUSES[$quote->status] }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">هنوز پیشنهادی نرسیده است. تأمین‌کنندگان معمولاً ظرف ۲۴ ساعت پاسخ می‌دهند.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
