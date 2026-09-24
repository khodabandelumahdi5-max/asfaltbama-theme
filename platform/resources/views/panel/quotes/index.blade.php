@extends('layouts.panel')
@section('title', 'پیشنهادهای من')

@section('content')
<div class="main-head"><h1>پیشنهادهای قیمت من</h1><a href="{{ route('rfqs.index') }}" class="btn btn-amber">استعلام‌های باز</a></div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>استعلام</th><th>مقدار</th><th>قیمت واحد</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
        <tbody>
        @forelse($quotes as $quote)
            <tr>
                <td><a href="{{ route('rfqs.show', $quote->rfq) }}"><b>{{ $quote->rfq->title }}</b></a></td>
                <td>{{ fa_number($quote->rfq->quantity, 2) }} {{ $quote->rfq->unit }}</td>
                <td>{{ toman($quote->unit_price) }}</td>
                <td><span class="badge {{ ['accepted' => 'badge-ok', 'rejected' => 'badge-bad'][$quote->status] ?? 'badge-amber' }}">{{ \App\Models\Quote::STATUSES[$quote->status] }}</span></td>
                <td class="small muted">{{ jdate($quote->created_at) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">هنوز پیشنهادی ارسال نکرده‌اید.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $quotes->links() }}</div>
@endsection
