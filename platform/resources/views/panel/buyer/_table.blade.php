<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>عنوان</th><th>مقدار</th><th>پیشنهادها</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
        <tbody>
        @forelse($rfqs as $rfq)
            <tr>
                <td><a href="{{ route('panel.buyer.rfqs.show', $rfq) }}"><b>{{ $rfq->title }}</b></a></td>
                <td>{{ fa_number($rfq->quantity, 2) }} {{ $rfq->unit }}</td>
                <td><span class="badge badge-amber">{{ fa_number($rfq->quotes_count) }}</span></td>
                <td>@if($rfq->isOpen())<span class="badge badge-ok">باز</span>@else<span class="badge">بسته</span>@endif</td>
                <td class="small muted">{{ jdate($rfq->created_at) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">هنوز استعلامی ثبت نکرده‌اید. <a href="{{ route('rfqs.create') }}" style="color:var(--info)">اولین استعلام را ثبت کنید</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
