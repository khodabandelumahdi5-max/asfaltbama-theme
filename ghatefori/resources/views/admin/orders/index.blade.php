@extends('layouts.admin')
@section('title', 'سفارش‌ها')

@section('content')
<div class="main-head">
    <h1>سفارش‌ها</h1>
    <form class="row">
        <select name="status" style="width:auto"><option value="">همه وضعیت‌ها</option>@foreach(\App\Models\Order::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
        <input name="q" value="{{ request('q') }}" placeholder="کد، نام یا موبایل" style="width:180px">
        <button class="btn btn-ghost btn-sm">فیلتر</button>
    </form>
</div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>کد</th><th>مشتری</th><th>شهر</th><th>مبلغ</th>@if(auth()->user()->isManager())<th>باقی‌مانده پس از هزینه متغیر</th>@endif<th>وضعیت</th><th>قول ارسال</th><th>ثبت</th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr>
                <td><a href="{{ route('admin.orders.show', $o) }}" class="code">{{ $o->code }}</a></td>
                <td>{{ $o->customer->name }}<div class="small muted ltr">{{ $o->customer->phone }}</div></td>
                <td>{{ $o->city }}</td>
                <td>{{ toman($o->total()) }}</td>
                @if(auth()->user()->isManager())
                    @php($cm = $o->contribution())
                    <td class="{{ $cm === null ? 'muted' : ($cm < 0 ? 'money-neg' : 'money-pos') }}">{{ $cm === null ? 'قیمت خرید نامشخص' : toman($cm) }}</td>
                @endif
                <td><span class="badge {{ ['awaiting_confirmation' => 'badge-accent', 'cancelled' => 'badge-bad', 'returned' => 'badge-bad', 'delivered' => 'badge-ok'][$o->status] ?? 'badge-info' }}">{{ \App\Models\Order::STATUSES[$o->status] }}</span></td>
                <td class="small {{ $o->promised_at?->isPast() && in_array($o->status, ['confirmed', 'paid']) ? 'overdue' : '' }}">{{ $o->promised_at ? jdate($o->promised_at, 'd MMM') : '—' }}</td>
                <td class="small muted">{{ jdate($o->created_at, 'd MMM، HH:mm') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">سفارشی نیست.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $orders->links() }}</div>
@endsection
