@extends('layouts.admin')
@section('title', 'خبرم کن')

@section('content')
<div class="main-head"><h1>درخواست‌های «خبرم کن»</h1></div>
<p class="small muted">کالاهایی که الان موجودند بالای فهرست‌اند. فقط وقتی تماس بگیرید که موجودی واقعی تأیید شده باشد.</p>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>نام</th><th>موبایل</th><th>کالا</th><th>وضعیت کالا</th><th>ثبت</th><th></th></tr></thead>
        <tbody>
        @forelse($alerts as $a)
            <tr>
                <td>{{ $a->name }}</td>
                <td class="ltr">{{ $a->phone }}</td>
                <td><a href="{{ route('admin.products.edit', $a->product) }}">{{ $a->product->name }}</a></td>
                <td>@include('partials.stock', ['product' => $a->product])</td>
                <td class="small muted">{{ jdate($a->created_at, 'd MMM') }}</td>
                <td>@if($a->product->stock_status === 'in_stock')
                    <form method="POST" action="{{ route('admin.alerts.notified', $a) }}">@csrf @method('PATCH')<button class="btn btn-accent btn-sm">تماس گرفتم</button></form>
                @endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">درخواستی نیست.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $alerts->links() }}</div>
@endsection
