<div class="grid g2">
    <div class="field"><label>نام *</label><input name="name" value="{{ old('name', $contact->name) }}" required></div>
    <div class="field"><label>سازمان / پیمانکار</label><input name="organization" value="{{ old('organization', $contact->organization) }}"></div>
    <div class="field"><label>موبایل</label><input name="phone" class="ltr" value="{{ old('phone', $contact->phone) }}"></div>
    <div class="field"><label>ایمیل</label><input type="email" name="email" class="ltr" value="{{ old('email', $contact->email) }}"></div>
    <div class="field"><label>مرحله</label>
        <select name="stage">@foreach(\App\Models\CrmContact::STAGES as $k => $l)<option value="{{ $k }}" @selected(old('stage', $contact->stage) === $k)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="field"><label>ارزش معامله (تومان)</label><input type="number" min="0" name="deal_value" value="{{ old('deal_value', $contact->deal_value) }}"></div>
</div>
<div class="field"><label>یادداشت</label><textarea name="notes" rows="3">{{ old('notes', $contact->notes) }}</textarea></div>
