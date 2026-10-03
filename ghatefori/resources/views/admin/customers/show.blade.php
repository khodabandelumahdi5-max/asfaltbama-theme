@extends('layouts.admin')
@section('title', $customer->name)

@section('content')
<div class="main-head">
    <div><h1>{{ $customer->name }}</h1><span class="small muted">{{ \App\Models\Customer::TYPES[$customer->type] }} · مشتری از {{ jdate($customer->created_at) }}</span></div>
    <a href="tel:{{ $customer->phone }}" class="btn btn-accent">📞 <span class="ltr">{{ $customer->phone }}</span></a>
</div>
<div class="grid layout-side" style="grid-template-columns:1fr 1fr;align-items:start">
<div>
    <div class="card mb">
        <h3>سفارش‌ها و خریدها</h3>
        @forelse($customer->orders as $o)
            <a href="{{ route('admin.orders.show', $o) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--line)">
                <div class="row between"><span class="code">{{ $o->code }}</span><span class="badge">{{ \App\Models\Order::STATUSES[$o->status] }}</span></div>
                <div class="small">{{ $o->items->map(fn ($i) => $i->product->name.' ('.($i->product->brand->name ?? '—').')')->implode('، ') }}</div>
                <div class="small muted">{{ jdate($o->created_at) }} · {{ toman($o->total()) }}
                    @if($o->cancel_reason) · لغو: {{ \App\Models\Order::CANCEL_REASONS[$o->cancel_reason] }}@endif
                    @if($o->return_reason) · مرجوعی: {{ \App\Models\Order::RETURN_REASONS[$o->return_reason] }}@endif</div>
            </a>
        @empty<p class="muted small">سفارشی ندارد.</p>@endforelse
    </div>
    <div class="card mb">
        <h3>استعلام‌ها و درخواست‌های تأمین‌نشده</h3>
        @forelse($customer->fitmentRequests as $f)
            <a href="{{ route('admin.fitment.show', $f) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--line)">
                {{ $f->product->name ?? \Illuminate\Support\Str::limit($f->description, 50) }}
                <div class="small muted">{{ jdate($f->created_at) }} · {{ \App\Models\FitmentRequest::STATUSES[$f->status] }}</div>
            </a>
        @empty<p class="muted small">موردی نیست.</p>@endforelse
    </div>
    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="card">
        @csrf @method('PUT')
        <h3>اطلاعات مشتری</h3>
        <div class="grid g2">
            <div class="field"><label>نام</label><input name="name" value="{{ $customer->name }}" required></div>
            <div class="field"><label>نوع</label><select name="type">@foreach(\App\Models\Customer::TYPES as $k => $l)<option value="{{ $k }}" @selected($customer->type === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label>شهر</label><input name="city" value="{{ $customer->city }}"></div>
            <div class="field"><label>منبع آشنایی</label><select name="source"><option value="">—</option>@foreach(\App\Models\Customer::SOURCES as $k => $l)<option value="{{ $k }}" @selected($customer->source === $k)>{{ $l }}</option>@endforeach</select></div>
        </div>
        <div class="field"><label>یادداشت (علت نخریدن، شکایت، ترجیحات)</label><textarea name="notes" rows="3">{{ $customer->notes }}</textarea></div>
        <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="marketing_consent" value="1" @checked($customer->marketing_consent)> مشتری با دریافت یادآوری و پیام تبلیغاتی موافق است</label></div>
        <button class="btn btn-accent">ذخیره</button>
    </form>
</div>
<div>
    <div class="card mb">
        <h3>خودروها</h3>
        @forelse($customer->vehicles as $cv)
            <div style="padding:6px 0;border-bottom:1px solid var(--line)"><b>{{ $cv->label() }}</b>
                <div class="small muted">@if($cv->vin)شاسی: <span class="code">{{ $cv->vin }}</span> · @endif آخرین سرویس: {{ $cv->last_service_date ? jdate($cv->last_service_date) : '—' }} @if($cv->last_service_km)در {{ fa_number($cv->last_service_km) }} کیلومتر@endif (اعلام مشتری)</div></div>
        @empty<p class="muted small">خودرویی ثبت نشده.</p>@endforelse
        <details class="mt"><summary class="small" style="cursor:pointer;color:var(--info)">＋ افزودن خودرو</summary>
            <form method="POST" action="{{ route('admin.customers.vehicles.store', $customer) }}" class="mt">@csrf
                <div class="field">@include('partials.vehicle-select', ['name' => 'vehicle_id', 'selected' => null, 'makes' => $makes])</div>
                <div class="grid g2">
                    <div class="field"><label>یا شرح</label><input name="description"></div>
                    <div class="field"><label>سال ساخت</label><input type="number" name="year"></div>
                    <div class="field"><label>شماره شاسی</label><input name="vin" class="ltr"></div>
                    <div class="field"><label>تاریخ آخرین سرویس</label><input type="date" name="last_service_date" class="ltr"></div>
                    <div class="field"><label>کیلومتر آخرین سرویس</label><input type="number" name="last_service_km"></div>
                </div>
                <button class="btn btn-sm">افزودن</button>
            </form>
        </details>
    </div>
    <div class="card">
        <h3>یادآوری سرویس</h3>
        @unless($customer->marketing_consent)<div class="warn mb">مشتری اجازه پیام نداده؛ یادآوری‌ها در صف کار نمایش داده نمی‌شوند.</div>@endunless
        @forelse($customer->reminders as $r)
            <div class="row between" style="padding:6px 0;border-bottom:1px solid var(--line)">
                <span @style(['text-decoration:line-through;color:var(--muted)' => $r->done_at])><b>{{ $r->title }}</b> — {{ jdate($r->due_on) }} @if($r->is_estimate)<span class="badge">تخمینی</span>@endif
                    <div class="small muted">منبع: {{ $r->basis }} @if($r->customerVehicle)· {{ $r->customerVehicle->label() }}@endif</div></span>
                @unless($r->done_at)<form method="POST" action="{{ route('admin.customers.reminders.done', [$customer, $r->id]) }}">@csrf @method('PATCH')<button class="btn btn-ghost btn-sm">✔</button></form>@endunless
            </div>
        @empty<p class="muted small">یادآوری‌ای نیست.</p>@endforelse
        <details class="mt"><summary class="small" style="cursor:pointer;color:var(--info)">＋ یادآوری جدید</summary>
            <form method="POST" action="{{ route('admin.customers.reminders.store', $customer) }}" class="mt">@csrf
                <div class="field"><label>عنوان</label><input name="title" required placeholder="بررسی وضعیت لنت جلو"></div>
                <div class="grid g2">
                    <div class="field"><label>خودرو</label><select name="customer_vehicle_id"><option value="">—</option>@foreach($customer->vehicles as $cv)<option value="{{ $cv->id }}">{{ $cv->label() }}</option>@endforeach</select></div>
                    <div class="field"><label>نوع قطعه</label><select name="part_category_id"><option value="">—</option>@foreach($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select></div>
                    <div class="field"><label>تاریخ</label><input type="date" name="due_on" class="ltr" required></div>
                    <div class="field"><label>منبع بازه</label><input name="basis" required placeholder="دفترچه سرویس سازنده / اعلام مشتری"></div>
                </div>
                <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="confirmed_mileage" value="1"> بر اساس کیلومتر فعلی تأییدشده (در غیر این صورت «تخمینی» ثبت می‌شود)</label></div>
                <p class="small muted">پیام یادآوری باید اول وضعیت کارکرد را بپرسد، نه اینکه تعویض را قطعی اعلام کند؛ و امکان لغو پیام داشته باشد.</p>
                <button class="btn btn-sm">ثبت</button>
            </form>
        </details>
    </div>
</div>
</div>
<style>@media(max-width:1000px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
