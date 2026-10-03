@extends('layouts.admin')
@section('title', 'تأمین‌کنندگان')

@section('content')
<div class="main-head"><h1>تأمین‌کنندگان (بنکداران)</h1></div>
<div class="grid layout-side" style="grid-template-columns:1.4fr 1fr;align-items:start">
    <div class="card-flat table-wrap">
        <table>
            <thead><tr><th>نام</th><th>تماس</th><th>شرایط پرداخت</th><th>مرجوعی</th><th>ارسال مستقیم</th><th>اقلام</th><th></th></tr></thead>
            <tbody>
            @forelse($suppliers as $s)
                <tr>
                    <td><b>{{ $s->name }}</b></td>
                    <td class="ltr small">{{ $s->phone }}</td>
                    <td class="small">{{ $s->payment_terms ?? '—' }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($s->return_terms, 60) ?: '—' }}</td>
                    <td>{{ $s->ships_direct ? 'بله' : 'خیر' }}</td>
                    <td>{{ fa_digits($s->offers_count) }}</td>
                    <td><a href="{{ route('admin.suppliers.index', ['edit' => $s->id]) }}" class="btn btn-ghost btn-sm">ویرایش</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">تأمین‌کننده‌ای ثبت نشده.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <form method="POST" action="{{ $editing->exists ? route('admin.suppliers.update', $editing) : route('admin.suppliers.store') }}" class="card">
        @csrf @if($editing->exists) @method('PUT') @endif
        <h3>{{ $editing->exists ? 'ویرایش '.$editing->name : 'تأمین‌کننده جدید' }}</h3>
        <div class="field"><label>نام</label><input name="name" value="{{ old('name', $editing->name) }}" required></div>
        <div class="field"><label>تلفن</label><input name="phone" class="ltr" value="{{ old('phone', $editing->phone) }}"></div>
        <div class="field"><label>شرایط پرداخت</label><input name="payment_terms" value="{{ old('payment_terms', $editing->payment_terms) }}" placeholder="نقد / چک ۳۰ روزه"></div>
        <div class="field"><label>شرایط تعویض، مرجوعی و خرابی (به‌ویژه قطعات برقی)</label><textarea name="return_terms" rows="3">{{ old('return_terms', $editing->return_terms) }}</textarea></div>
        <div class="field"><label>یادداشت</label><textarea name="notes" rows="2">{{ old('notes', $editing->notes) }}</textarea></div>
        <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="ships_direct" value="1" @checked(old('ships_direct', $editing->ships_direct))> مستقیم برای مشتری ارسال می‌کند (مسئولیت کنترل کالا و فاکتور را روشن کنید)</label></div>
        <button class="btn btn-accent">ذخیره</button>
    </form>
</div>
<style>@media(max-width:1000px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
