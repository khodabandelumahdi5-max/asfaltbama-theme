@extends('layouts.panel')
@section('title', 'پروفایل شرکت')

@section('content')
<div class="main-head"><h1>پروفایل شرکت</h1><a href="{{ route('companies.show', $company) }}" class="btn btn-ghost btn-sm" target="_blank">مشاهده غرفه ↗</a></div>
<form method="POST" action="{{ route('panel.company.update') }}" class="card" style="max-width:760px">
    @csrf @method('PUT')
    <div class="grid g2">
        <div class="field"><label>نام شرکت *</label><input name="name" value="{{ old('name', $company->name) }}" required></div>
        <div class="field"><label>شهر</label><input name="city" value="{{ old('city', $company->city) }}"></div>
        <div class="field"><label>تلفن</label><input name="phone" class="ltr" value="{{ old('phone', $company->phone) }}"></div>
        <div class="field"><label>سال تأسیس (شمسی)</label><input type="number" name="founded_year" value="{{ old('founded_year', $company->founded_year) }}" placeholder="1390"></div>
    </div>
    <div class="field"><label>وب‌سایت</label><input name="website" class="ltr" value="{{ old('website', $company->website) }}" placeholder="https://"></div>
    <div class="field"><label>معرفی شرکت</label><textarea name="description" rows="6">{{ old('description', $company->description) }}</textarea></div>
    <button class="btn btn-amber">ذخیره</button>
</form>
@endsection
